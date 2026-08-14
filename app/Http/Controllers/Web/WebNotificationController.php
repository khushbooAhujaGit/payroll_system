<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class WebNotificationController extends Controller
{
    /**
     * Marks one notification read, then forwards the user to its target URL
     * (a plain link can't both mark-read and navigate in one request).
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notif = $request->user()->notifications()->findOrFail($notification);
        if (!$notif->read_at) {
            $notif->markAsRead();
        }

        return redirect($notif->data['url'] ?? route('dashboard'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
