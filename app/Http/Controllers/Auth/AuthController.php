<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return nexus_view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();
        $this->recordLogin($request);

        return redirect()->intended(route('storefront.account.index'));
    }

    public function showRegister(): View
    {
        abort_if(nexus_demo(), 404);

        return nexus_view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_if(nexus_demo(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'terms' => ['accepted'],
        ]);

        $user = User::query()->create([
            'store_id' => \App\Models\Store::default()?->id,
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => 'customer',
            'wholesale_status' => 'none',
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $this->recordLogin($request);

        return redirect()->route('storefront.account.index')->with('status', 'Welcome to '.nexus_setting('store.name', 'ShopKeeprr').'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }

    private function recordLogin(Request $request): void
    {
        $request->user()?->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
    }
}
