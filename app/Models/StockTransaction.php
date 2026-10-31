<?php

namespace App\Models;

use App\Enums\StockTransactionStatus;
use App\Enums\StockTransactionType;
use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    const UPDATED_AT = null; // table has only created_at

    protected $fillable = [
        'transaction_number', 'transaction_type', 'status', 'source_type', 'source_id',
        'occurred_at', 'created_by', 'posted_by', 'posted_at',
        'reversal_of_transaction_id', 'notes',
    ];

    protected $casts = [
        'transaction_type' => StockTransactionType::class,
        'status'           => StockTransactionStatus::class,
        'occurred_at'      => 'datetime',
        'posted_at'        => 'datetime',
    ];

    public function items()    { return $this->hasMany(StockTransactionItem::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
    public function poster()   { return $this->belongsTo(User::class, 'posted_by'); }
    public function reversalOf() { return $this->belongsTo(StockTransaction::class, 'reversal_of_transaction_id'); }
}