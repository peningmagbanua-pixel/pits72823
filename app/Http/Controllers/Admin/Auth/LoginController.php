<?php

namespace App\Http\Controllers\Admin\Auth;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Traits\AdminActivityLogsTrait;

class LoginController extends Controller
{
    use AdminActivityLogsTrait;
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::guard('web')->attempt($credentials)) {

            $request->session()->regenerate();

            /** @var \App\Models\Admin\User $user */
            $user = Auth::guard('web')->user();

            if (!$user->is_active) {
                Auth::guard('web')->logout(); 
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('admin.login')
                    ->with('error', 'Account is inactive.');
            }

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            $this->logAdminActivity('logged_in', 'Admin logged in successfully.', ['email' => $user->email]);

            return redirect()->route('admin.dashboard');
        }

        return back()->withInput($request->only('email'))
            ->with('error', 'Invalid email or password.');
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('web')->user();

        if ($user) {
            $this->logAdminActivity('logged_out', 'Admin logged out.', ['email' => $user->email]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('message', 'Logged out successfully');
    }
}
