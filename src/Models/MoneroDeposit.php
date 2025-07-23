<?php

namespace ItHealer\LaravelMonero\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ItHealer\LaravelMonero\Casts\BigDecimalCast;
use ItHealer\LaravelMonero\Facades\Monero;

class MoneroDeposit extends Model
{
    protected $fillable = [
        'wallet_id',
        'account_id',
        'address_id',
        'txid',
        'amount',
        'block_height',
        'confirmations',
        'time_at',
    ];

    protected $casts = [
        'amount' => BigDecimalCast::class,
        'confirmations' => 'integer',
        'time_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Monero::getModelWallet(), 'wallet_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Monero::getModelAccount(), 'address_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Monero::getModelAddress(), 'address_id');
    }
}
