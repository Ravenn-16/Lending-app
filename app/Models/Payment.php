<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model
{
    use \App\Models\Concerns\HasReference;
    const UPDATED_AT = null;
    protected $fillable = ['loan_id', 'amount', 'payment_date', 'status', 'voided_at'];
    protected $casts = ['amount' => 'decimal:2', 'voided_at' => 'datetime'];
    public function loan() { return $this->belongsTo(Loan::class); }
}
