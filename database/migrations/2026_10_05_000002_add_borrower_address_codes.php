<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('province_code', 20)->nullable();
            $table->string('city_code', 20)->nullable();
            $table->string('barangay_code', 20)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('borrowers', fn (Blueprint $table) => $table->dropColumn(['province_code','city_code','barangay_code']));
    }
};
