<?php

namespace App\Services;

use App\Mail\LoginCode;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Hash, Mail, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginCodeService
{
    public function email(Admin $admin): ?string
    {
        return config('login_code.owner_email') ?: $admin->login_email;
    }

    public function setupOwner(Request $request): ?Admin
    {
        $setup = $request->session()->get('email_setup');
        if (!config('login_code.enabled') || !$setup || $setup['expires'] <= now()->timestamp) return null;
        $admin = Admin::find($setup['admin_id']);
        return $admin && $admin->username === config('login_code.owner_username') && !$this->email($admin) &&
            hash_equals($setup['password_hash'], $admin->password_hash) ? $admin : null;
    }

    public function clear(Request $request): void
    {
        if ($id = $request->session()->pull('login_challenge')) Cache::forget('login-code:'.$id);
        $request->session()->forget('email_setup');
    }

    public function pending(Request $request): ?array
    {
        $id = $request->session()->get('login_challenge');
        $challenge = $id ? Cache::get('login-code:'.$id) : null;
        if (!$challenge || $challenge['expires'] <= now()->timestamp) return null;
        return $challenge;
    }

    public function send(Request $request, Admin $admin, ?string $registrationEmail = null): void
    {
        $registering = $registrationEmail !== null;
        if ($registering && $this->email($admin)) {
            throw ValidationException::withMessages(['email' => 'An email is already registered. Please sign in again.']);
        }
        $email = $registrationEmail ?? $this->email($admin);
        if (!config('login_code.enabled') || $admin->username !== config('login_code.owner_username') || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['username' => 'Email verification is not configured for this account. Contact the app administrator.']);
        }
        // Never write login codes to logs or silently fall back to a log mailer.
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        if (!in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'mailgun', 'resend'], true)) {
            throw ValidationException::withMessages(['username' => 'Email delivery is not configured. Contact the app administrator.']);
        }
        $key = 'login-code-send:'.$admin->id;
        Cache::lock($key.':lock', 30)->block(5, function () use ($request, $admin, $email, $key, $registering) {
            if (RateLimiter::tooManyAttempts($key, 1) || RateLimiter::tooManyAttempts($key.':hour', 10)) {
                throw ValidationException::withMessages(['code' => 'Please wait before requesting another code. You can request one per minute, up to 10 per hour.']);
            }
            RateLimiter::hit($key, 60);
            RateLimiter::hit($key.':hour', 3600);
            $this->clear($request);
            $code = (string) random_int(100000, 999999);
            $id = Str::random(64);
            $challenge = ['admin_id' => $admin->id, 'password_hash' => $admin->password_hash,
                'registering' => $registering,
                'email' => $email, 'hash' => Hash::make($code), 'attempts' => 0, 'expires' => now()->addMinutes(10)->timestamp];
            try {
                Mail::to($email)->send(new LoginCode($code));
            } catch (\Throwable $exception) {
                // Mail exceptions can contain message content or transport credentials.
                throw ValidationException::withMessages(['code' => 'The code could not be sent. Wait a minute and sign in again, or contact the app administrator.']);
            }
            Cache::put('login-code:'.$id, $challenge, 600);
            $request->session()->put('login_challenge', $id);
        });
    }

    public function verify(Request $request, string $code): ?Admin
    {
        $id = $request->session()->get('login_challenge');
        if (!$id) return null;
        return Cache::lock('login-code-verify:'.$id, 10)->block(5, function () use ($request, $code, $id) {
            $challenge = $this->pending($request);
            if (!$challenge) { $this->clear($request); return null; }
            $admin = Admin::find($challenge['admin_id']);
            if (!config('login_code.enabled') || !$admin || $admin->username !== config('login_code.owner_username') ||
                (!empty($challenge['registering']) ? (bool) $this->email($admin) : $challenge['email'] !== $this->email($admin)) || !hash_equals($challenge['password_hash'], $admin->password_hash)) {
                $this->clear($request); return null;
            }
            if (!Hash::check($code, $challenge['hash'])) {
                $challenge['attempts']++;
                if ($challenge['attempts'] >= 5) $this->clear($request);
                else Cache::put('login-code:'.$id, $challenge, max(1, $challenge['expires'] - now()->timestamp));
                return null;
            }
            if (!empty($challenge['registering'])) {
                // A second browser cannot replace an address registered in the meantime.
                $updated = Admin::whereKey($admin->id)->whereNull('login_email')
                    ->where('password_hash', $challenge['password_hash'])->update(['login_email' => $challenge['email']]);
                if (!$updated) { $this->clear($request); return null; }
                $admin->refresh();
            }
            $this->clear($request);
            return $admin;
        });
    }
}
