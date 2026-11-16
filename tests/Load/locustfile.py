"""Fifty browser-like HTTP sessions against the isolated IPG load-test server.
Run ONLY against http://127.0.0.1:8001 and with LOADTEST_FIXTURES set.
"""
from __future__ import annotations

import itertools
import json
import os
import random
import re
from datetime import date, timedelta
from html import unescape
from http.client import HTTPConnection
from pathlib import Path

from locust import HttpUser, between, events, task
from locust.exception import StopTest, StopUser

FIXTURES = Path(os.environ.get("IPG_LOADTEST_FIXTURES", "storage/app/loadtest/fixtures.json"))
if not FIXTURES.is_file():
    raise RuntimeError("Missing fixture file. Run loadtest:seed first, and set IPG_LOADTEST_FIXTURES if necessary.")
DATA = json.loads(FIXTURES.read_text())
FACILITIES = DATA["facility_ids"]
PASSWORD = DATA["password"]
COUNTERS = {"booker": itertools.count(1), "viewer": itertools.count(1), "admin": itertools.count(1)}
TOKEN = re.compile(r'<input\b[^>]*name=[\'"]_token[\'"][^>]*value=[\'"]([^\'"]+)', re.I)
BOOKING_LINK = re.compile(r'(?:https?://[^/\'"\s]+)?/bookings/(\d+)(?:[\'"?])')
ADMIN_PENDING = re.compile(r'(?:https?://[^/\'"\s]+)?/admin/bookings/(\d+)/approve')
RESCHEDULE_LINK = re.compile(r'(?:https?://[^/\'"\s]+)?/admin/reschedules/(\d+)(?:[\'"?])')


def csrf(html: str) -> str:
    match = TOKEN.search(html)
    if match:
        return unescape(match.group(1))
    # CSRF meta tag is present on authenticated pages if a form token is absent.
    meta = re.search(r'<meta\s+name=[\'"]csrf-token[\'"]\s+content=[\'"]([^\'"]+)', html, re.I)
    if meta:
        return unescape(meta.group(1))
    raise ValueError("CSRF token missing; page may be an error response")


def redirect_to(response, segment: str) -> bool:
    return response.status_code == 302 and segment in response.headers.get("Location", "")


@events.test_start.add_listener
def validate_target(environment, **kwargs):
    # StopTest is propagated by Locust's event handler; RuntimeError only
    # logs a listener failure and can still allow users to be spawned.
    if (environment.host or "").rstrip("/") != "http://127.0.0.1:8001":
        environment.process_exit_code = 2
        raise StopTest("Only http://127.0.0.1:8001 is permitted.")
    connection = HTTPConnection("127.0.0.1", 8001, timeout=5)
    try:
        connection.request("GET", "/__loadtest/ready")
        response = connection.getresponse()
        if response.status != 200 or json.loads(response.read(2048)) != {
            "environment": "loadtest", "database": "loadtest.sqlite"
        }:
            raise ValueError("Invalid sandbox handshake")
    except Exception as exc:
        environment.process_exit_code = 2
        raise StopTest("Refusing to run: the isolated load-test server did not respond.") from exc
    finally:
        connection.close()


class RealSession(HttpUser):
    abstract = True
    wait_time = between(1, 4)
    role = "viewer"

    def on_start(self):
        index = next(COUNTERS[self.role])
        limit = {"booker": 25, "viewer": 20, "admin": 5}[self.role]
        if index > limit:
            raise RuntimeError(f"Too many {self.role} virtual users; expected {limit}")
        self.index = index
        self.token = ""
        self.email = f"loadtest-{self.role}-{index:02d}@example.invalid"
        with self.client.get("/login", name="GET /login", catch_response=True) as res:
            if res.status_code != 200:
                res.failure(f"Login page returned {res.status_code}")
                raise StopUser()
            self.token = csrf(res.text)
        with self.client.post("/login", data={"_token": self.token, "email": self.email,
                                             "password": PASSWORD}, allow_redirects=False,
                              name="POST /login", catch_response=True) as res:
            if not redirect_to(res, "/dashboard"):
                res.failure(f"Login rejected: {res.status_code} {res.headers.get('Location', '')}")
                raise StopUser()
        with self.client.get("/dashboard", name="GET /dashboard", catch_response=True) as res:
            if res.status_code != 200:
                res.failure(f"Dashboard returned {res.status_code}")
            else:
                self.token = csrf(res.text)

    def check_page(self, uri: str, label: str):
        with self.client.get(uri, name=label, catch_response=True) as res:
            if res.status_code != 200:
                res.failure(f"Unexpected HTTP {res.status_code}")
            return res

    def send_put(self, path: str, extra: dict, label: str, expected_redirect: str, *, reschedule=False):
        with self.client.post(path, data={"_token": self.token, "_method": "PUT", **extra},
                              headers={"Accept": "application/json"}, allow_redirects=False,
                              name=label, catch_response=True) as res:
            if redirect_to(res, expected_redirect):
                return True
            if res.status_code == 422:
                errors = res.json().get("errors", {})
                expected = (
                    "This rescheduling request has already been processed." if reschedule
                    else "This booking has already been processed."
                )
                if any(str(message).startswith(expected) for messages in errors.values() for message in messages):
                    # A different admin reached the same request first.
                    res.success()
                    self.environment.events.request.fire(
                        request_type="BUSINESS", name="Stale decision refused",
                        response_time=0, response_length=0, exception=None,
                    )
                    return False
            res.failure(f"Unexpected PUT redirect/status: {res.status_code} {res.headers.get('Location', '')}")
            return False


class BookingStaff(RealSession):
    role = "booker"
    fixed_count = 25
    weight = 1

    def on_start(self):
        super().on_start()
        self.submitted = 0
        self.my_bookings = []
        # All 25 bookers attempt a booking immediately after login.
        self.submit_booking()

    def submit_booking(self):
        # Rotation spreads normal traffic across 10 facilities/days. Periodic
        # contention below deliberately makes competing requests for one slot.
        self.submitted += 1
        clash = self.submitted % 4 == 0
        offset = 12 + self.index + ((self.submitted - 1) // 4) * 28
        day = date.today() + timedelta(days=offset)
        facility = FACILITIES[(self.index + self.submitted) % len(FACILITIES)]
        hour = 9 + ((self.submitted - 1) % 4) * 2
        if clash:
            day = date.today() + timedelta(days=7)
            facility = FACILITIES[0]
            hour = 10
        self.check_page("/staff/bookings/create", "GET /staff/bookings/create")
        data = {"_token": self.token, "facility_id": facility,
                "start_date": day.isoformat(), "end_date": day.isoformat(),
                "start_time": f"{hour:02d}:00", "end_time": f"{hour+1:02d}:00",
                "purpose": f"[LOADTEST] booking by {self.email} #{self.submitted}"}
        label = "POST /staff/bookings [conflict probe]" if clash else "POST /staff/bookings"
        with self.client.post("/staff/bookings", data=data, name=label,
                              headers={"Accept": "application/json"},
                              allow_redirects=False, catch_response=True) as res:
            if redirect_to(res, "/staff/my-bookings"):
                res.success()
            elif clash and res.status_code == 422 and "already has a pending or approved booking" in res.text:
                res.success()
                self.environment.events.request.fire(
                    request_type="BUSINESS", name="Conflicting slot refused",
                    response_time=0, response_length=0, exception=None,
                )
            else:
                res.failure(f"Submission not confirmed: {res.status_code} {res.headers.get('Location', '')}")

    @task(4)
    def check_calendar(self):
        self.check_page(f"/calendar?facility={random.choice(FACILITIES)}", "GET /calendar")

    @task(3)
    def view_bookings(self):
        self.check_page("/staff/my-bookings", "GET /staff/my-bookings")

    @task(2)
    def book_another(self):
        self.submit_booking()

    @task(1)
    def request_change(self):
        with self.client.get("/staff/my-bookings", name="GET /staff/my-bookings", catch_response=True) as res:
            if res.status_code != 200:
                res.failure(f"HTTP {res.status_code}")
                return
            ids = list(dict.fromkeys(BOOKING_LINK.findall(res.text)))
        if not ids:
            return
        booking_id = random.choice(ids)
        page = self.check_page(f"/staff/bookings/{booking_id}/reschedule", "GET /reschedule form")
        if page.status_code != 200 or "Submit reschedule request" not in page.text:
            return
        day = date.today() + timedelta(days=60 + self.index + random.randint(0, 10))
        with self.client.post(f"/staff/bookings/{booking_id}/reschedule",
                              data={"_token": self.token, "start_date": day.isoformat(),
                                    "end_date": day.isoformat(), "start_time": "17:00", "end_time": "18:00"},
                              allow_redirects=False, name="POST /reschedule request", catch_response=True) as res:
            if not redirect_to(res, f"/bookings/{booking_id}"):
                res.failure(f"Rescheduling not confirmed: {res.status_code}")


class BrowsingStaff(RealSession):
    role = "viewer"
    fixed_count = 20
    weight = 1

    @task(5)
    def browse_calendar(self):
        self.check_page(f"/calendar?facility={random.choice(FACILITIES)}", "GET /calendar")

    @task(3)
    def dashboard(self):
        self.check_page("/dashboard", "GET /dashboard")

    @task(2)
    def browse_form(self):
        self.check_page("/staff/bookings/create", "GET /staff/bookings/create")

    @task(1)
    def my_bookings(self):
        self.check_page("/staff/my-bookings", "GET /staff/my-bookings")


class AdminUser(RealSession):
    role = "admin"
    fixed_count = 5
    weight = 1

    @task(5)
    def decide_pending(self):
        with self.client.get("/admin/bookings/manage?tab=pending", name="GET /admin/bookings/manage?tab=pending", catch_response=True) as res:
            if res.status_code != 200:
                res.failure(f"HTTP {res.status_code}")
                return
            ids = list(dict.fromkeys(ADMIN_PENDING.findall(res.text)))
        if not ids:
            return
        bid = random.choice(ids)
        action = random.choices(["approve", "reject", "cancel"], [4, 3, 3])[0]
        params = {"admin_remarks": "Load test simulated administrative decision"} if action != "approve" else {}
        if self.send_put(f"/admin/bookings/{bid}/{action}", params,
                         f"PUT /admin/bookings/{action}", "/admin/bookings/manage?tab=pending"):
            self.check_page(f"/bookings/{bid}", "GET /bookings/status verify")

    @task(2)
    def review_reschedule(self):
        page = self.check_page("/admin/bookings/manage?tab=reschedules", "GET /admin/bookings/manage?tab=reschedules")
        ids = [x for x in dict.fromkeys(RESCHEDULE_LINK.findall(page.text))]
        if not ids:
            return
        rid = random.choice(ids)
        self.check_page(f"/admin/reschedules/{rid}", "GET /admin/reschedules/review")
        decision = random.choice(["approve", "reject"])
        self.send_put(f"/admin/reschedules/{rid}", {"decision": decision,
                         "review_notes": "Simulated schedule decision"},
                      "PUT /admin/reschedules/decision", "/admin/bookings/manage?tab=reschedules", reschedule=True)

    @task(3)
    def inspect_history(self):
        self.check_page("/admin/bookings", "GET /admin/bookings")
        self.check_page("/admin/load-test", "GET /admin/load-test")
