<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create() { return view('auth.login'); }
    public function store(Request $request, \App\Services\LoginCodeService $codes)
    {
        $data = $request->validate(['username' => ['bail', 'required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'], 'password' => ['bail', 'required', 'string', 'max:72', 'not_regex:/\x00/']]);
        $codes->clear($request);
        if (config('login_code.enabled')) {
            $admin = \App\Models\Admin::where('username', $data['username'])->first();
            if (!$admin || !Hash::check($data['password'], $admin->password_hash)) throw ValidationException::withMessages(['username' => 'Invalid credentials.']);
            $request->session()->regenerate();
            if ($admin->username === config('login_code.owner_username') && !$codes->email($admin)) {
                $request->session()->put('email_setup', ['admin_id' => $admin->id, 'password_hash' => $admin->password_hash, 'expires' => now()->addMinutes(10)->timestamp]);
                return redirect()->route('login.email');
            }
            $codes->send($request, $admin);
            return redirect()->route('login.code');
        }
        if (!Auth::attempt($data)) throw ValidationException::withMessages(['username' => 'Invalid credentials.']);
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }
    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
    public function settings() { return view('auth.settings'); }
    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => ['bail', 'required', 'string', 'max:72', 'current_password'],
            'password' => ['bail', 'required', 'string', 'min:12', 'confirmed', 'different:current_password', 'not_regex:/\x00/', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) $fail('Password must be at most 72 bytes.');
            }], 'password_confirmation' => ['required', 'string', 'max:72']]);
        $request->user()->update(['password_hash' => Hash::make($data['password'])]);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return back()->with('success', 'Password changed successfully.');
    }
}
