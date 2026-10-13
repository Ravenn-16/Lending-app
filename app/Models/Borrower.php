<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Borrower extends Model
{
    use \App\Models\Concerns\HasReference;
    protected $fillable = ['name', 'location', 'contact_number', 'province_code', 'city_code', 'barangay_code'];
    public function loans() { return $this->hasMany(Loan::class); }
}
