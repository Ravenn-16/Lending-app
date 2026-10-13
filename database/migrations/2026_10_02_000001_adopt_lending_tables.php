<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('admins')) Schema::create('admins', function (Blueprint $table) {
            $table->increments('id'); $table->string('username', 100)->unique(); $table->string('password_hash'); $table->timestamps();
        });
        if (!Schema::hasTable('borrowers')) Schema::create('borrowers', function (Blueprint $table) {
            $table->increments('id'); $table->string('name')->index(); $table->string('location')->nullable(); $table->string('contact_number', 50)->nullable(); $table->timestamps();
        });
        if (!Schema::hasTable('loans')) Schema::create('loans', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedInteger('borrower_id'); $table->decimal('principal', 12, 2); $table->date('date_borrowed')->index(); $table->timestamps();
            $table->foreign('borrower_id')->references('id')->on('borrowers')->restrictOnDelete();
        });
        if (!Schema::hasTable('payments')) Schema::create('payments', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedInteger('loan_id'); $table->decimal('amount', 12, 2); $table->date('payment_date')->index();
            $table->enum('status', ['POSTED', 'VOIDED'])->default('POSTED')->index(); $table->timestamp('created_at')->nullable(); $table->timestamp('voided_at')->nullable();
            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
        });
        if (!Schema::hasTable('ledger_entries')) Schema::create('ledger_entries', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedInteger('loan_id');
            $table->enum('type', ['PRINCIPAL', 'INTEREST', 'PAYMENT', 'PAYMENT_REVERSAL', 'ADJUSTMENT']);
            $table->decimal('amount', 12, 2); $table->date('effective_date'); $table->unsignedInteger('payment_id')->nullable(); $table->text('notes')->nullable(); $table->timestamp('created_at')->nullable();
            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->index(['loan_id', 'effective_date']);
        });
    }
    public function down(): void
    {
        // Existing financial tables may predate Laravel. Rollbacks must not destroy them.
        throw new RuntimeException('This migration adopts financial tables and cannot be rolled back. Restore a verified backup instead.');
    }
};
