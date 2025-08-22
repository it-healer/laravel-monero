<?php

namespace ItHealer\LaravelMonero\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ItHealer\LaravelMonero\Api\Api;
use ItHealer\LaravelMonero\Facades\Monero;

class MoneroNode extends Model
{
    protected ?Api $_api = null;

    protected $fillable = [
        'name',
        'title',
        'host',
        'port',
        'username',
        'password',
        'daemon',
        'pid',
        'sync_at',
        'worked',
        'worked_data',
        'available',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'port' => 'integer',
        'password' => 'encrypted',
        'pid' => 'integer',
        'sync_at' => 'datetime',
        'worked' => 'boolean',
        'worked_data' => 'json',
        'available' => 'boolean',
    ];

    public function wallets(): HasMany
    {
        return $this->hasMany(Monero::getModelWallet(), 'node_id');
    }

    public function isLocal(): bool
    {
        return !empty($this->daemon);
    }

    public function api(): Api
    {
        if( !$this->_api ) {
            /** @var class-string<Api> $model */
            $model = config('monero.models.api');
            $api = new $model(
                host: $this->host,
                port: $this->port,
                username: $this->username,
                password: $this->password,
                daemon: $this->daemon,
            );

            $api->getVersion();

            $this->_api = $api;
        }

        return $this->_api;
    }
}
