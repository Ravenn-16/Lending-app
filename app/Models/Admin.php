<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class Admin extends Authenticatable
{
    use \App\Models\Concerns\HasReference;
    protected $fillable = ['username', 'password_hash'];
    protected $hidden = ['password_hash'];
    public function getAuthPasswordName() { return 'password_hash'; }
    public function getRememberTokenName() { return ''; }
}
