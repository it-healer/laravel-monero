<?php

namespace ItHealer\LaravelMonero\Facades;

use Illuminate\Support\Facades\Facade;

class Monero extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ItHealer\LaravelMonero\Monero::class;
    }
}
