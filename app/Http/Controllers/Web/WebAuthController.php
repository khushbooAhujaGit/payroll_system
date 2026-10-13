<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class WebAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('employee:id,date_of_leaving')->where('email', $credentials['email'])->first();

        // Lazily enforce date_of_leaving here too (not just the nightly
        // accounts:deactivate-leavers sweep) so access is cut off on their
        // very next login attempt, not whenever the schedule next runs.
        if ($user && $user->is_active && $user->employee?->date_of_leaving
            && $user->employee->date_of_leaving->lt(now()->startOfDay())) {
            $user->update(['is_active' => false]);
        }

        if (!$user || !$user->is_active) {
            return back()
                ->withErrors(['email' => 'Invalid credentials or account deactivated.'])
                ->onlyInput('email');
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'login',
            'module'      => 'auth',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'description' => 'User logged in via web',
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
