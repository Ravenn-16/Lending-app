<?php

namespace Tests\Feature;

use App\Mail\LoginCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\{Cache, DB, Hash, Mail};
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_creates_owner_once_and_never_resets_password(): void
    {
        $hash = password_hash('Production-password-123!', PASSWORD_BCRYPT, ['cost' => 12]);
        config(['login_code.owner_username' => 'owner', 'deployment.owner_password_hash' => $hash]);
        $this->artisan('admin:bootstrap')->assertSuccessful();
        $owner = Admin::firstOrFail();
        $this->assertTrue(Hash::check('Production-password-123!', $owner->password_hash));
        $owner->update(['password_hash' => Hash::make('Changed-password-123!')]);
        config(['deployment.owner_password_hash' => null]);
        $this->artisan('admin:bootstrap')->assertSuccessful();
        $this->assertTrue(Hash::check('Changed-password-123!', $owner->fresh()->password_hash));
        config(['login_code.owner_username' => 'typo']);
        $this->artisan('admin:bootstrap')->assertFailed();
        $this->assertDatabaseCount('admins', 1);
    }

    public function test_bootstrap_rejects_missing_or_plaintext_credentials(): void
    {
        config(['login_code.owner_username' => 'owner', 'deployment.owner_password_hash' => 'plaintext-password']);
        $this->artisan('admin:bootstrap')->assertFailed();
        config(['deployment.owner_password_hash' => null]);
        $this->artisan('admin:bootstrap')->assertFailed();
        $this->assertDatabaseCount('admins', 0);
    }

    public function test_database_login_challenge_survives_cache_recreation_and_cannot_be_replayed(): void
    {
        config(['cache.default' => 'database', 'login_code.enabled' => true,
            'login_code.owner_username' => 'owner', 'login_code.owner_email' => 'owner@example.test',
            'mail.default' => 'resend']);
        Mail::fake();
        Admin::create(['username' => 'owner', 'password_hash' => Hash::make('Production-password-123!')]);
        $this->post('/login', ['username' => 'owner', 'password' => 'Production-password-123!'])->assertRedirect('/login/code');
        $this->assertGuest();
        $id = session('login_challenge');
        $code = Mail::sent(LoginCode::class)->last()->code;
        Cache::forgetDriver('database');
        $this->assertNotNull(Cache::get('login-code:'.$id));
        $first = Cache::lock('deployment-lock', 30);
        $this->assertTrue($first->get());
        $second = Cache::lock('deployment-lock', 30);
        $this->assertFalse($second->get());
        $first->release();
        $this->assertTrue($second->get());
        $second->release();
        $this->post('/login/code', ['code' => $code])->assertRedirect('/');
        $this->assertAuthenticated();
        Cache::forgetDriver('database');
        $this->assertNull(Cache::get('login-code:'.$id));
        $this->post('/logout');
        $this->withSession(['login_challenge' => $id])->post('/login/code', ['code' => $code])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_database_sessions_persist_across_handler_instances(): void
    {
        $first = new DatabaseSessionHandler(DB::connection(), 'sessions', 120);
        $first->write('persistent-session', 'encrypted-session-payload');
        $second = new DatabaseSessionHandler(DB::connection(), 'sessions', 120);
        $this->assertSame('encrypted-session-payload', $second->read('persistent-session'));
        $second->destroy('persistent-session');
        $this->assertSame('', $first->read('persistent-session'));
    }

    public function test_resend_transport_can_be_constructed_without_smtp(): void
    {
        config(['services.resend.key' => 'test-key-not-a-real-secret']);
        $transport = Mail::mailer('resend')->getSymfonyTransport();
        $this->assertInstanceOf(\Illuminate\Mail\Transport\ResendTransport::class, $transport);
    }
}
