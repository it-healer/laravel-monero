<?php

namespace ItHealer\LaravelMonero\WebhookHandlers;

use ItHealer\LaravelMonero\Models\MoneroDeposit;

interface WebhookHandlerInterface
{
    public function handle(MoneroDeposit $deposit): void;
}