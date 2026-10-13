<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Services\LoginCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginCodeController extends Controller
{
    public function emailForm(Request $request, LoginCodeService $codes)
    {
        if (!$codes->setupOwner($request)) return redirect()->route('login');
        return response()->view('auth.email')->header('Cache-Control', 'no-store');
    }

    public function registerEmail(Request $request, LoginCodeService $codes)
    {
        $admin = $codes->setupOwner($request);
        if (!$admin) return redirect()->route('login');
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:254', 'confirmed']]);
        $codes->send($request, $admin, $data['email']);
        return redirect()->route('login.code');
    }

    public function show(Request $request, LoginCodeService $codes)
    {
        if (!$codes->pending($request)) return redirect()->route('login')->withErrors(['username' => 'Please sign in again to receive a new code.']);
        return response()->view('auth.code')->header('Cache-Control', 'no-store');
    }

    public function verify(Request $request, LoginCodeService $codes)
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']]);
        $admin = $codes->verify($request, $data['code']);
        if (!$admin) return redirect()->route($codes->pending($request) ? 'login.code' : 'login')
            ->withErrors(['code' => 'The code is incorrect or has expired. After five incorrect attempts, sign in again.']);
        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->put('verified_owner_email', $codes->email($admin));
        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request, LoginCodeService $codes)
    {
        $challenge = $codes->pending($request);
        $admin = $challenge ? Admin::find($challenge['admin_id']) : null;
        if (!$admin || !hash_equals($challenge['password_hash'], $admin->password_hash)) {
            $codes->clear($request);
            return redirect()->route('login');
        }
        $codes->send($request, $admin, !empty($challenge['registering']) ? $challenge['email'] : null);
        return redirect()->route('login.code')->with('code_sent', true);
    }

    public function cancel(Request $request, LoginCodeService $codes)
    {
        $codes->clear($request);
        $request->session()->regenerate();
        return redirect()->route('login');
    }
}
