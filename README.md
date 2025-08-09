![Logo](docs/logo.jpeg)

<a href="https://packagist.org/packages/it-healer/laravel-monero" target="_blank">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/packagist/v/it-healer/laravel-monero.svg?style=flat&cacheSeconds=3600" alt="Latest Version on Packagist">
</a>

<a href="https://packagist.org/packages/it-healer/laravel-monero" target="_blank">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/packagist/dt/it-healer/laravel-monero.svg?style=flat&cacheSeconds=3600" alt="Total Downloads">
</a>

# Laravel Monero

Organization of payment acceptance and automation of payments of XMR coins on the Monero blockchain.

### Installation

You can install the package via composer:
```bash
composer require it-healer/laravel-monero
```

After you can run installer using command:
```bash
php artisan monero:install
```

Optional, you can install Monero Wallet RPC using command:
```bash
php artisan monero:wallet-rpc
```

And run migrations:
```bash
php artisan migrate
```

Register Service Provider and Facade in app, edit `config/app.php`:
```php
'providers' => ServiceProvider::defaultProviders()->merge([
    ...,
    \ItHealer\LaravelMonero\MoneroServiceProvider::class,
])->toArray(),

'aliases' => Facade::defaultAliases()->merge([
    ...,
    'Monero' => \ItHealer\LaravelMonero\Facades\Monero::class,
])->toArray(),
```

Add cron job, in file `app/Console/Kernel` in method `schedule(Schedule $schedule)` add
```
Schedule::command('monero:sync')
    ->everyMinute()
    ->runInBackground();
```

You must setup Supervisor, create file `/etc/supervisor/conf.d/monero.conf` with content (change user and paths):
```
[program:monero]
process_name=%(program_name)s
command=php /home/forge/example.com/artisan monero
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/home/forge/example.com/monero.log
stopwaitsecs=3600
```

### Commands
Monero Node sync with all wallets in here.
```bash
php artisan monero:node-sync [NODE ID]
```

Monero Wallet sync.
```bash
php artisan monero:wallet-sync [WALLET ID]
```


### For Developers
Command for build JS script:
```bash
npm i
npm run build
```

## Support

- Telegram: [@biodynamist](https://t.me/biodynamist)
- WhatsApp: [+905516294716](https://wa.me/905516294716)
- Web: [it-healer.com](https://it-healer.com)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [IT-HEALER](https://github.com/it-healer)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

