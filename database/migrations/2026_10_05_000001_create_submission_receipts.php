<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('submission_receipts', function (Blueprint $table) {
            $table->uuid('key')->primary();
            $table->unsignedInteger('admin_id')->index();
            $table->string('operation', 30);
            $table->char('payload_hash', 64);
            $table->unsignedInteger('result_id')->nullable();
            $table->timestamp('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('submission_receipts'); }
};
