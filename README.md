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

Check status of monero-wallet-rpc processes.
```bash
php artisan monero:status --all              # Check all nodes
php artisan monero:status [NODE ID or NAME]  # Check specific node
php artisan monero:status 1 --method=full    # Full diagnostics
```

### Process Monitoring

The module provides comprehensive monitoring capabilities for `monero-wallet-rpc` processes.

#### Available Check Methods

| Method | Speed            | Reliability | Use Case                        |
|--------|------------------|-------------|---------------------------------|
| `pid`  | Very fast        | Low         | Quick existence check           |
| `port` | Fast             | Medium      | Port availability check         |
| `api`  | Medium           | High        | Recommended for production      |
| `full` | Slow             | High        | Full diagnostics                |

#### Usage in Code

**Check node status:**
```php
use ItHealer\LaravelMonero\Facades\Monero;

$node = MoneroNode::find(1);

// Check status (without saving to DB)
$result = Monero::checkNodeStatus($node, 'api');

if ($result['status']) {
    echo "Process is running";
} else {
    echo "Process is down: " . $result['details']['message'];
}
```

**Check and update status in database:**
```php
$node = Monero::updateNodeStatus($node, 'api');

if ($node->worked) {
    echo "Process is operational";
    // Details: $node->worked_data
}
```

**Check all nodes:**
```php
$stats = Monero::checkAllNodesStatus('api');

echo "Total: {$stats['total']}, Working: {$stats['working']}, Failed: {$stats['failed']}";
```

**Health check endpoint:**
```php
Route::get('/api/monero/health', function () {
    $stats = Monero::checkAllNodesStatus('api');
    return response()->json($stats, $stats['failed'] === 0 ? 200 : 503);
});
```

#### Automatic Monitoring

The `php artisan monero` supervisor process automatically checks and updates the status of all processes every N seconds (configured via `monero.wallet_rpc.watcher_period`).

Status is stored in the database:
- `worked` (boolean) - whether the process is running
- `worked_data` (json) - detailed information about the last check

**Example of reading status from database:**
```php
$node = MoneroNode::find(1);

if ($node->worked) {
    echo "Last check: {$node->worked_data['last_check']}";
} else {
    echo "Error: {$node->worked_data['message']}";
}
```

For more detailed information, see:
- [PROCESS_MONITORING.md](PROCESS_MONITORING.md) - Complete documentation
- [EXAMPLES_STATUS_CHECK.md](EXAMPLES_STATUS_CHECK.md) - Quick code examples
- [STATUS_CHECK_SUMMARY.md](STATUS_CHECK_SUMMARY.md) - Summary of changes


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

