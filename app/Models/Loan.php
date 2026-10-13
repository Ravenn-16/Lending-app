<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Loan extends Model
{
    use \App\Models\Concerns\HasReference;
    protected $fillable = ['borrower_id', 'principal', 'date_borrowed'];
    protected $casts = ['principal' => 'decimal:2'];
    public function borrower() { return $this->belongsTo(Borrower::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function ledgerEntries() { return $this->hasMany(LedgerEntry::class)->orderBy('effective_date')->orderBy('id'); }
}
