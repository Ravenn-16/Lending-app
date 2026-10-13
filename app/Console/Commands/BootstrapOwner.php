<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class BootstrapOwner extends Command
{
    protected $signature = 'admin:bootstrap';
    protected $description = 'Create the configured deployment owner once from a password hash';

    public function handle(): int
    {
        $username = config('login_code.owner_username');
        if (!is_string($username) || trim($username) === '' || strlen($username) > 100) {
            $this->error('Set a valid OWNER_USERNAME.');
            return self::FAILURE;
        }
        if (Admin::where('username', $username)->exists()) {
            $this->info('Owner already exists; credentials were not changed.');
            return self::SUCCESS;
        }
        if (Admin::exists()) {
            $this->error('Administrators already exist. Set OWNER_USERNAME to the intended existing account.');
            return self::FAILURE;
        }
        $hash = config('deployment.owner_password_hash');
        $info = is_string($hash) ? password_get_info($hash) : [];
        if (($info['algoName'] ?? '') !== 'bcrypt' || ($info['options']['cost'] ?? 0) < 12) {
            $this->error('Generate OWNER_PASSWORD_HASH locally with php artisan admin:password-hash.');
            return self::FAILURE;
        }
        Admin::firstOrCreate(['username' => $username], ['password_hash' => $hash]);
        $this->info('Deployment owner created. Remove OWNER_PASSWORD_HASH from Render after the first successful login.');
        return self::SUCCESS;
    }
}
