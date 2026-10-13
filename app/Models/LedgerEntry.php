<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LedgerEntry extends Model
{
    use \App\Models\Concerns\HasReference;
    const UPDATED_AT = null;
    protected $fillable = ['loan_id', 'type', 'amount', 'effective_date', 'payment_id', 'notes'];
    protected $casts = ['amount' => 'decimal:2'];
    public function loan() { return $this->belongsTo(Loan::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
}
