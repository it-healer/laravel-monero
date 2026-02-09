# Быстрые примеры проверки статуса процессов

## Команды CLI

```bash
# Проверить все ноды
php artisan monero:status --all

# Проверить конкретную ноду
php artisan monero:status 1
php artisan monero:status main-node

# Полная диагностика
php artisan monero:status 1 --method=full
```

## В коде приложения

### Простая проверка

```php
use ItHealer\LaravelMonero\Facades\Monero;

$node = MoneroNode::find(1);

// Проверить статус
$result = Monero::checkNodeStatus($node);

if ($result['status']) {
    echo "✓ Процесс работает";
} else {
    echo "✗ Процесс не работает: {$result['details']['message']}";
}
```

### Проверка и сохранение в БД

```php
use ItHealer\LaravelMonero\Facades\Monero;

$node = MoneroNode::find(1);

// Проверить и обновить статус в БД
$node = Monero::updateNodeStatus($node);

if ($node->worked) {
    echo "✓ Процесс работает";
    print_r($node->worked_data);
}
```

### Проверка всех нод

```php
use ItHealer\LaravelMonero\Facades\Monero;

$stats = Monero::checkAllNodesStatus();

echo "Всего нод: {$stats['total']}\n";
echo "Работают: {$stats['working']}\n";
echo "Не работают: {$stats['failed']}\n";

foreach ($stats['nodes'] as $node) {
    echo "{$node['name']}: {$node['message']}\n";
}
```

### Получение статуса из БД

```php
$node = MoneroNode::find(1);

// Проверить последний сохраненный статус
if ($node->worked) {
    echo "Процесс работает";
    echo "Последняя проверка: {$node->worked_data['last_check']}";
} else {
    echo "Процесс не работает";
    echo "Ошибка: {$node->worked_data['message']}";
}
```

## В контроллерах

### API endpoint для проверки статуса

```php
use Illuminate\Http\Request;
use ItHealer\LaravelMonero\Facades\Monero;
use ItHealer\LaravelMonero\Models\MoneroNode;

class MoneroNodeController extends Controller
{
    // GET /api/monero/nodes/{node}/status
    public function status(MoneroNode $node)
    {
        $result = Monero::checkNodeStatus($node, 'api');
        return response()->json($result);
    }

    // GET /api/monero/nodes/status
    public function statusAll()
    {
        $stats = Monero::checkAllNodesStatus();
        return response()->json($stats);
    }

    // GET /api/monero/health
    public function health()
    {
        $total = MoneroNode::where('available', true)->count();
        $working = MoneroNode::where('available', true)
            ->where('worked', true)
            ->count();

        return response()->json([
            'status' => $working === $total ? 'healthy' : 'degraded',
            'working' => $working,
            'total' => $total,
        ], $working === $total ? 200 : 503);
    }
}
```

## Методы проверки

| Метод | Скорость | Надежность | Когда использовать |
|-------|----------|------------|-------------------|
| `pid` | ⚡⚡⚡ Очень быстро | ⭐⭐ Низкая | Быстрая проверка существования |
| `port` | ⚡⚡ Быстро | ⭐⭐⭐ Средняя | Проверка занятости порта |
| `api` | ⚡ Средне | ⭐⭐⭐⭐⭐ Высокая | **Рекомендуется для production** |
| `full` | 🐌 Медленно | ⭐⭐⭐⭐⭐ Высокая | Диагностика проблем |

## Мониторинг в Cron

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Проверять каждые 5 минут
    $schedule->call(function () {
        $stats = Monero::checkAllNodesStatus('api');

        if ($stats['failed'] > 0) {
            Log::error('Monero nodes are down', $stats);
            // Отправить уведомление
        }
    })->everyFiveMinutes();
}
```

## Интеграция с Laravel Horizon/Queue

```php
use Illuminate\Bus\Queueable;
use ItHealer\LaravelMonero\Facades\Monero;

class CheckMoneroNodesHealth implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $stats = Monero::checkAllNodesStatus('api');

        if ($stats['failed'] > 0) {
            // Отправить уведомление
            Notification::route('mail', 'admin@example.com')
                ->notify(new MoneroNodesDownNotification($stats));
        }
    }
}

// В планировщике
$schedule->job(new CheckMoneroNodesHealth)->everyFiveMinutes();
```
