/* Booking fields: rotating 06:00-first quick choices, exact-minute entry and all-day mode.
 * Native type=time cannot guarantee a locale-independent option order; our select can. */
(function () {
    'use strict';
    document.querySelectorAll('[data-booking-time]').forEach(function (section) {
        var allDay = section.querySelector('[data-all-day]');
        var fields = section.querySelector('[data-time-inputs]');
        var option = section.querySelector('[data-time-option]');
        var groups = Array.from(section.querySelectorAll('[data-time-field]'));
        if (!allDay || !fields || !option || !groups.length) return;
        option.hidden = false;
        var overnightHint = section.querySelector('[data-overnight-hint]');
        function showOvernightHint() {
            var times = groups.map(function (group) {
                var input = group.querySelector('[data-exact-time]');
                return (input.disabled ? group.querySelector('[data-time-select]') : input).value;
            });
            if (overnightHint) overnightHint.hidden = allDay.checked || !times[0] || !times[1] || times[1] > times[0];
        }

        function sync() {
            fields.hidden = allDay.checked;
            groups.forEach(function (group) {
                var select = group.querySelector('[data-time-select]');
                var exact = group.querySelector('[data-exact-time]');
                var box = group.querySelector('[data-exact-time-container]');
                var toggle = group.querySelector('[data-exact-time-toggle]');
                var isExact = !box.hidden;
                select.disabled = allDay.checked || isExact;
                exact.disabled = allDay.checked || !isExact;
                // Only one control submits a value. Blank = all day when JS is disabled.
                select.required = !allDay.checked && !isExact;
                exact.required = !allDay.checked && isExact;
                toggle.disabled = allDay.checked;
                toggle.setAttribute("aria-expanded", String(isExact));
            });
            showOvernightHint();
        }

        groups.forEach(function (group) {
            var select = group.querySelector('[data-time-select]');
            var exact = group.querySelector('[data-exact-time]');
            var box = group.querySelector('[data-exact-time-container]');
            var toggle = group.querySelector('[data-exact-time-toggle]');
            toggle.hidden = false;
            select.addEventListener('change', showOvernightHint);
            exact.addEventListener('input', showOvernightHint);
            toggle.addEventListener('click', function () {
                if (box.hidden) {
                    exact.value = select.value || exact.value;
                    // Move field name instead of ever submitting duplicate values.
                    exact.name = select.name;
                    select.removeAttribute('name');
                    box.hidden = false;
                    toggle.textContent = 'Use suggested times';
                    sync();
                    exact.focus();
                } else {
                    // Preserve exact minute on switch back by inserting a selectable option.
                    var name = exact.name;
                    var value = exact.value;
                    select.name = name;
                    exact.removeAttribute('name');
                    if (value && !Array.from(select.options).some(function (o) { return o.value === value; })) {
                        select.add(new Option(value + ' (exact minute)', value), 1);
                    }
                    if (value) select.value = value;
                    box.hidden = true;
                    toggle.textContent = 'Enter exact minute';
                    sync();
                    select.focus();
                }
            });
        });
        allDay.addEventListener('change', sync);
        sync();
        window.addEventListener('pageshow', sync);
    });

    var start = document.getElementById('start_date');
    var end = document.getElementById('end_date');
    if (!start || !end) return;
    function syncDates() {
        if (!start.value) return;
        end.min = start.value;
        var limit = new Date(start.value + 'T00:00:00Z');
        if (Number.isNaN(limit.getTime())) return;
        limit.setUTCDate(limit.getUTCDate() + 29);
        end.max = limit.toISOString().slice(0, 10);
        if (end.value && end.value < start.value) end.value = start.value;
    }
    start.addEventListener('change', syncDates);
    window.addEventListener('pageshow', syncDates);
    syncDates();
})();
