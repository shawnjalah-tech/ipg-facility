<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationInboxController extends Controller
{
    public function index(Request $request)
    {
        $notices = $request->user()->notifications()->latest()->paginate(20);
        return view('notifications.index', compact('notices'));
    }

    public function read(Request $request, string $notification)
    {
        $notice = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $notice->markAsRead();
        return back()->with('status', 'Notification marked as read.');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return back()->with('status', 'All notifications marked as read.');
    }
}
