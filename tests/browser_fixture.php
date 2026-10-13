<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = storage_path('framework/testing/browser.sqlite');
if (file_exists($path)) exit("Fixture exists; use it or explicitly choose a fresh test file.\n");
if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
touch($path);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
Illuminate\Support\Facades\DB::purge();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$admin = App\Models\Admin::create(['username' => 'test_admin', 'password_hash' => Illuminate\Support\Facades\Hash::make('Test-password-123!')]);
$borrower = App\Models\Borrower::create(['name' => 'Browser Test Person', 'location' => 'Manila']);
$loan = app(App\Services\LoanService::class)->createLoan($borrower->id, '1000', today()->toDateString(), $admin->id);
app(App\Services\LoanService::class)->recordPayment($loan->id, '100', today()->toDateString(), $admin->id);
echo "Browser fixture ready: ".$path.PHP_EOL;
