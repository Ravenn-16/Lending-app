<?php
use App\Models\Admin;
use Illuminate\Support\Facades\{Artisan, Hash};
Artisan::command('admin:password-hash', function () {
    $password = $this->secret('New production owner password (12 to 72 bytes)');
    if (strlen($password ?? '') < 12 || strlen($password) > 72) {
        $this->error('Invalid password length.'); return 1;
    }
    if ($password !== $this->secret('Confirm password')) {
        $this->error('Passwords do not match.'); return 1;
    }
    $this->line(password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
})->purpose('Generate a deployment password hash without modifying local accounts');
Artisan::command('admin:create {username}', function () {
    $username = $this->argument('username');
    if (strlen($username) > 100 || trim($username) === '' || Admin::where('username', $username)->exists()) {
        $this->error('Choose a unique username of at most 100 characters.'); return 1;
    }
    $password = $this->secret('Password (12 to 72 bytes)');
    if (strlen($password ?? '') < 12 || strlen($password) > 72) { $this->error('Invalid password length.'); return 1; }
    if ($password !== $this->secret('Confirm password')) { $this->error('Passwords do not match.'); return 1; }
    Admin::create(['username' => $username, 'password_hash' => Hash::make($password)]);
    $this->info('Administrator created.');
})->purpose('Create an administrator without changing existing accounts');
