<?php
namespace Tests\Feature;

use App\Mail\LoginCode;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Hash, Mail};
use Tests\TestCase;

class LoginCodeTest extends TestCase
{
    use RefreshDatabase;
    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['login_code.enabled' => true, 'login_code.owner_username' => 'owner', 'login_code.owner_email' => 'owner@example.test', 'mail.default' => 'smtp']);
        Mail::fake();
        $this->owner = Admin::create(['username' => 'owner', 'password_hash' => Hash::make('Owner-password-123!')]);
    }

    private function start(): string
    {
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!'])->assertRedirect('/login/code');
        $this->assertGuest();
        $mail = Mail::sent(LoginCode::class)->last();
        $this->assertTrue($mail->hasTo('owner@example.test'));
        return $mail->code;
    }

    public function test_password_alone_cannot_access_app_and_valid_code_is_consumed(): void
    {
        $code = $this->start();
        $id = session('login_challenge');
        $this->assertNotSame($code, Cache::get('login-code:'.$id)['hash']);
        $this->get('/login/code')->assertOk()->assertSee('Check your email');
        $this->get('/reports')->assertRedirect('/login');
        $this->getJson('/api/loans/1')->assertUnauthorized();
        $this->post('/login/code', ['code' => $code])->assertRedirect('/reports')->assertSessionMissing('login_challenge');
        $this->assertAuthenticatedAs($this->owner);
        $this->assertNull(Cache::get('login-code:'.$id));
        $this->get('/reports')->assertOk();
        $this->post('/logout');
        $this->withSession(['login_challenge' => $id])->post('/login/code', ['code' => $code])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_sends_nothing_and_five_wrong_codes_end_challenge(): void
    {
        $this->post('/login', ['username' => 'owner', 'password' => 'wrong'])->assertSessionHasErrors();
        Mail::assertNothingSent();
        $this->start();
        for ($i = 0; $i < 5; $i++) $this->post('/login/code', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertNull(session('login_challenge'));
        $this->assertGuest();
    }

    public function test_expiry_password_change_and_cancel_prevent_verification(): void
    {
        $code = $this->start();
        $this->travel(11)->minutes();
        $this->post('/login/code', ['code' => $code])->assertRedirect('/login');
        $this->assertGuest();
        $code = $this->start();
        $this->owner->update(['password_hash' => Hash::make('Changed-password-123!')]);
        $this->post('/login/code', ['code' => $code])->assertRedirect('/login');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->owner->update(['password_hash' => Hash::make('Owner-password-123!')]);
        $this->start();
        $id = session('login_challenge');
        $this->post('/login/code/cancel')->assertRedirect('/login');
        $this->assertNull(Cache::get('login-code:'.$id));
    }

    public function test_resend_is_limited_and_replaces_old_challenge(): void
    {
        $oldCode = $this->start();
        $oldId = session('login_challenge');
        $this->post('/login/code/resend')->assertSessionHasErrors('code');
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post('/login/code/resend')->assertRedirect('/login/code');
        $this->assertNull(Cache::get('login-code:'.$oldId));
        $this->assertNotSame($oldId, session('login_challenge'));
        Mail::assertSentCount(2);
        $this->post('/login/code', ['code' => Mail::sent(LoginCode::class)->last()->code])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_missing_configuration_and_mail_failure_do_not_bypass_code(): void
    {
        config(['login_code.owner_email' => 'invalid-address']);
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!'])->assertSessionHasErrors();
        $this->assertGuest();
        config(['login_code.owner_email' => 'owner@example.test', 'mail.default' => 'log']);
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!'])->assertSessionHasErrors();
        Mail::assertNothingSent();
        config(['mail.default' => 'smtp']);
        Mail::swap(\Mockery::mock()->shouldReceive('to')->andReturnSelf()->shouldReceive('send')->andThrow(new \RuntimeException('SMTP failure'))->getMock());
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNull(session('login_challenge'));
    }

    public function test_existing_session_requires_code_when_feature_is_enabled(): void
    {
        $this->actingAs($this->owner)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_owner_registers_email_only_after_password_and_code_then_uses_it_next_login(): void
    {
        config(['login_code.owner_email' => null]);
        $this->get('/login/email')->assertRedirect('/login');
        $this->post('/login/email', ['email' => 'stranger@example.test', 'email_confirmation' => 'stranger@example.test'])->assertRedirect('/login');
        Mail::assertNothingSent();
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!'])->assertRedirect('/login/email');
        $this->get('/login/email')->assertOk()->assertSee('Set up your email');
        $this->post('/login/email', ['email' => 'her@example.test', 'email_confirmation' => 'typo@example.test'])->assertSessionHasErrors('email');
        $this->post('/login/email', ['email' => 'her@example.test', 'email_confirmation' => 'her@example.test'])->assertRedirect('/login/code');
        $this->assertNull($this->owner->fresh()->login_email);
        $this->assertGuest();
        $this->post('/login/code', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertNull($this->owner->fresh()->login_email);
        $mail = Mail::sent(LoginCode::class)->last();
        $this->assertTrue($mail->hasTo('her@example.test'));
        $this->post('/login/code', ['code' => $mail->code])->assertRedirect('/');
        $this->assertSame('her@example.test', $this->owner->fresh()->login_email);
        $this->get('/settings')->assertOk()->assertSee('her@example.test');
        $this->post('/logout');
        $this->travel(61)->seconds();
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!'])->assertRedirect('/login/code');
        $this->get('/login/email')->assertRedirect('/login');
        $this->post('/login/email', ['email' => 'replacement@example.test', 'email_confirmation' => 'replacement@example.test'])->assertRedirect('/login');
        $this->assertSame('her@example.test', $this->owner->fresh()->login_email);
        $this->assertTrue(Mail::sent(LoginCode::class)->last()->hasTo('her@example.test'));
    }

    public function test_expired_setup_and_setup_after_password_change_are_rejected(): void
    {
        config(['login_code.owner_email' => null]);
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!']);
        $this->travel(11)->minutes();
        $this->post('/login/email', ['email' => 'her@example.test', 'email_confirmation' => 'her@example.test'])->assertRedirect('/login');
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!']);
        $this->owner->update(['password_hash' => Hash::make('Changed-password-123!')]);
        $this->get('/login/email')->assertRedirect('/login');
        Mail::assertNothingSent();
    }

    public function test_pending_registration_cannot_replace_an_email_saved_elsewhere(): void
    {
        config(['login_code.owner_email' => null]);
        $this->post('/login', ['username' => 'owner', 'password' => 'Owner-password-123!']);
        $this->post('/login/email', ['email' => 'first@example.test', 'email_confirmation' => 'first@example.test']);
        $code = Mail::sent(LoginCode::class)->last()->code;
        Admin::whereKey($this->owner->id)->update(['login_email' => 'verified@example.test']);
        $this->post('/login/code', ['code' => $code])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame('verified@example.test', $this->owner->fresh()->login_email);
    }
}
