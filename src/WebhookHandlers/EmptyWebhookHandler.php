<?php

namespace ItHealer\LaravelMonero\WebhookHandlers;

use ItHealer\LaravelMonero\Models\MoneroAddress;
use Illuminate\Support\Facades\Log;
use ItHealer\LaravelMonero\Models\MoneroAccount;
use ItHealer\LaravelMonero\Models\MoneroDeposit;
use ItHealer\LaravelMonero\Models\MoneroIntegratedAddress;
use ItHealer\LaravelMonero\Models\MoneroWallet;

class EmptyWebhookHandler implements WebhookHandlerInterface
{
    public function handle(MoneroDeposit $deposit): void {
        Log::error('Monero Wallet '.$deposit->wallet->name.', account '.$deposit->account->base_address.', address '.$deposit->address->address);
    }
}