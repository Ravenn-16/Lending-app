<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admins', fn (Blueprint $table) => $table->string('login_email', 254)->nullable());
    }

    public function down(): void
    {
        Schema::table('admins', fn (Blueprint $table) => $table->dropColumn('login_email'));
    }
};
