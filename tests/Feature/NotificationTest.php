<?php

namespace Tests\Feature;

use App\Models\{Admin, Borrower, Loan, LedgerEntry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_bell_remains_visible_without_due_loans(): void
    {
        $admin = Admin::create(['username' => 'notifications', 'password_hash' => bcrypt('Test-password-123!')]);
        $this->actingAs($admin)->get('/')->assertOk()
            ->assertSee('id="notification-center"', false)
            ->assertSee('aria-label="Notifications"', false)
            ->assertSee('No new notifications.')
            ->assertSee('View transaction history');
    }

    public function test_due_reminders_follow_existing_status_boundaries_and_exclude_paid_loans(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8)->startOfDay());
        $admin = Admin::create(['username' => 'notifications', 'password_hash' => bcrypt('Test-password-123!')]);
        $borrower = Borrower::create(['name' => 'Reminder borrower']);
        foreach (['2026-09-07', '2026-09-08', '2026-09-11', '2026-09-12'] as $date) {
            Loan::create(['borrower_id' => $borrower->id, 'principal' => '1000.00', 'date_borrowed' => $date]);
        }
        $paid = Loan::create(['borrower_id' => $borrower->id, 'principal' => '1000.00', 'date_borrowed' => '2026-09-10']);
        LedgerEntry::create(['loan_id' => $paid->id, 'type' => 'PAYMENT', 'amount' => '1100.00', 'effective_date' => '2026-10-07', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get('/settings')->assertOk()
            ->assertSee('2 loans are due today or within the next 3 days.')
            ->assertSee(route('loans.index', ['status' => 'DUE_SOON']), false);
        $this->travelBack();
    }

    public function test_action_feedback_is_rendered_in_shared_notification_region(): void
    {
        $admin = Admin::create(['username' => 'notifications', 'password_hash' => bcrypt('Test-password-123!')]);
        $this->actingAs($admin)->withSession(['success' => 'Borrower updated successfully.'])->get('/settings')
            ->assertOk()->assertSee('id="notification-region"', false)
            ->assertSee('Borrower updated successfully.')->assertSee('data-dismiss-notification', false)
            ->assertDontSee('Upcoming payments');
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['username' => 'Invalid credentials.']))])
            ->get('/settings')->assertOk()->assertSee('Invalid credentials.')->assertSee('role="alert"', false);
    }
}
