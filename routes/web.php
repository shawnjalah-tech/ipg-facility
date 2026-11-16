<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BookingApprovalController;
use App\Http\Controllers\Admin\BookingCreationController;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\BookingRescheduleController as AdminRescheduleController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\FacilityClosureController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\LoadTestDashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingDetailsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationInboxController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RecurringBookingController;
use App\Http\Controllers\Staff\BookingController as StaffBookingController;
use App\Http\Controllers\Staff\BookingRescheduleController as StaffRescheduleController;
use App\Support\LoadTestSandbox;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (guest only)
|--------------------------------------------------------------------------
*/
// A safety handshake used by the local load simulator. This never exposes live data.
Route::get('/__loadtest/ready', function () {
    abort_unless(LoadTestSandbox::active(), 404);

    return response()->json(['environment' => 'loadtest', 'database' => 'loadtest.sqlite']);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'form'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated (admin + staff)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications', [NotificationInboxController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationInboxController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationInboxController::class, 'readAll'])->name('notifications.read-all');
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('calendar/day', [CalendarController::class, 'day'])->name('calendar.day');
    Route::get('/recurring', [RecurringBookingController::class, 'index'])->name('recurring.index');
    Route::get('/recurring/{series}', [RecurringBookingController::class, 'show'])->name('recurring.show');
    Route::get('/bookings/{booking}', [BookingDetailsController::class, 'show'])->name('bookings.show');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
/*
|--------------------------------------------------------------------------
| Staff only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('bookings/create', [StaffBookingController::class, 'create'])->name('bookings.create');
        Route::get('bookings/{booking}/reschedule', [StaffRescheduleController::class, 'create'])->name('bookings.reschedule.create');
        Route::post('bookings/{booking}/reschedule', [StaffRescheduleController::class, 'store'])->name('bookings.reschedule.store');
        Route::delete('reschedules/{reschedule}', [StaffRescheduleController::class, 'withdraw'])->name('reschedules.withdraw');
        Route::post('bookings', [StaffBookingController::class, 'store'])->name('bookings.store');
        Route::get('my-bookings', [StaffBookingController::class, 'myBookings'])->name('my-bookings');
        Route::put('bookings/{booking}/cancel', [StaffBookingController::class, 'cancel'])->name('bookings.cancel');
    });
/*
|--------------------------------------------------------------------------
| Admin only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('recurring/{series}/decide', [RecurringBookingController::class, 'decide'])->name('recurring.decide');
        Route::get('load-test', [LoadTestDashboardController::class, 'index'])->name('load-test.index');
        Route::get('system/health', [SystemHealthController::class, 'index'])->name('system.health');
        // Phase 3.1: one queue; retain legacy route names/URLs for bookmarks and notices.
        Route::get('bookings/manage', [BookingManagementController::class, 'index'])->name('bookings.manage');
        Route::get('attention', fn () => redirect()->route('admin.bookings.manage', ['tab' => 'pending']))->name('attention.index');
        Route::get('bookings/create', [BookingCreationController::class, 'create'])->name('bookings.create');
        Route::post('bookings', [BookingCreationController::class, 'store'])->name('bookings.store');
        Route::get('bookings/export', [BookingDetailsController::class, 'export'])->name('bookings.export');
        Route::get('reschedules', fn () => redirect()->route('admin.bookings.manage', ['tab' => 'reschedules'] + request()->only('q', 'page')))->name('reschedules.index');
        Route::get('reschedules/{reschedule}', [AdminRescheduleController::class, 'show'])->name('reschedules.show');
        Route::put('reschedules/{reschedule}', [AdminRescheduleController::class, 'decide'])->name('reschedules.decide');
        Route::resource('categories', CategoryController::class);
        Route::get('facility-closures', [FacilityClosureController::class, 'index'])->name('facility-closures.index');
        Route::post('facility-closures', [FacilityClosureController::class, 'store'])->name('facility-closures.store');
        Route::delete('facility-closures/{closure}', [FacilityClosureController::class, 'destroy'])->name('facility-closures.destroy');
        Route::get('staff-search', [BookingCreationController::class, 'searchStaff'])->middleware('throttle:120,1')->name('staff-search');
        Route::resource('facilities', FacilityController::class);
        // Bookings (admin)
        Route::get('bookings/pending', fn () => redirect()->route('admin.bookings.manage', ['tab' => 'pending'] + request()->only('q', 'page')))->name('bookings.pending');
        Route::get('bookings', [BookingApprovalController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}/edit-status', [BookingApprovalController::class, 'editStatus'])->name('bookings.edit-status');
        Route::put('bookings/{booking}/status', [BookingApprovalController::class, 'updateStatus'])->name('bookings.update-status');
        Route::put('bookings/{booking}/approve', [BookingApprovalController::class, 'approve'])->name('bookings.approve');
        Route::put('bookings/{booking}/reject', [BookingApprovalController::class, 'reject'])->name('bookings.reject');
        Route::put('bookings/{booking}/cancel', [BookingApprovalController::class, 'adminCancel'])->name('bookings.cancel');
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
        Route::get('reports/monthly', [ReportController::class, 'index'])->name('reports.monthly');
        Route::get('reports/monthly/download', [ReportController::class, 'download'])->name('reports.monthly.download');
    });
