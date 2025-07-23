<?php

namespace ItHealer\LaravelMonero\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ItHealer\LaravelEthereumModule\Enums\EthereumModel;
use ItHealer\LaravelEthereumModule\Facades\Ethereum;
use ItHealer\LaravelEthereumModule\Models\EthereumTransaction;
use ItHealer\LaravelMonero\Casts\BigDecimalCast;
use ItHealer\LaravelMonero\Facades\Monero;

class MoneroAddress extends Model
{
    protected $fillable = [
        'wallet_id',
        'account_id',
        'address',
        'address_index',
        'title',
        'balance',
        'unlocked_balance',
        'sync_at',
        'available',
    ];

    protected $casts = [
        'address_index' => 'integer',
        'balance' => BigDecimalCast::class,
        'unlocked_balance' => BigDecimalCast::class,
        'sync_at' => 'datetime',
        'available' => 'boolean',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Monero::getModelWallet(), 'wallet_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Monero::getModelAccount(), 'account_id');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Monero::getModelDeposit(), 'address_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Monero::getModelTransaction(), 'address', 'address');
    }
}
