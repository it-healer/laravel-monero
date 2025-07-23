<?php

namespace ItHealer\LaravelMonero\Concerns;

use ItHealer\LaravelMonero\Facades\Monero;
use ItHealer\LaravelMonero\Models\MoneroAccount;
use ItHealer\LaravelMonero\Models\MoneroWallet;

trait Accounts
{
    public function createAccount(MoneroWallet $wallet, ?string $title = null): MoneroAccount
    {
        return Monero::generalAtomicLock($wallet, function() use ($wallet, $title) {
            $api = $wallet->node->api();

            if( !$wallet->node->isLocal() ) {
                $api->openWallet($wallet->name, $wallet->password);
            }

            $createAccount = $api->createAccount();

            $account = $wallet->accounts()->create([
                'title' => $title,
                'base_address' => $createAccount['address'],
                'account_index' => $createAccount['account_index'],
            ]);

            $account->addresses()->create([
                'wallet_id' => $wallet->id,
                'address' => $createAccount['address'],
                'address_index' => 0,
                'title' => 'Primary Address',
            ]);

            return $account;
        });
    }
}