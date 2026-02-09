# Мониторинг процессов monero-wallet-rpc

Модуль предоставляет несколько способов проверки статуса запущенных процессов `monero-wallet-rpc`.

## Методы проверки

### 1. **PID** - Проверка по идентификатору процесса
Быстрая проверка существования процесса по сохраненному PID.

**Плюсы:**
- Очень быстро
- Не требует сетевых запросов

**Минусы:**
- PID может быть переиспользован другим процессом
- Не гарантирует, что процесс работает корректно

### 2. **Port** - Проверка занятости порта
Проверяет, занят ли RPC порт ноды.

**Плюсы:**
- Быстро
- Показывает, что что-то слушает порт

**Минусы:**
- Порт может быть занят другим процессом
- Не проверяет работоспособность API

### 3. **API** - Проверка через RPC API (Рекомендуется)
Отправляет тестовый запрос к API monero-wallet-rpc.

**Плюсы:**
- Самая надежная проверка
- Проверяет реальную работоспособность
- Получает версию процесса

**Минусы:**
- Немного медленнее других методов
- Требует сетевого подключения

### 4. **Full** - Полная проверка всеми методами
Последовательно выполняет все три проверки и возвращает детальную информацию.

**Плюсы:**
- Максимально полная информация о состоянии
- Полезна для диагностики проблем

**Минусы:**
- Самый медленный метод

## Использование через Artisan команды

### Проверка всех нод

```bash
# Проверить все ноды методом API (рекомендуется)
php artisan monero:status --all

# Проверить все ноды с полной диагностикой
php artisan monero:status --all --method=full

# Проверить только по PID (быстро)
php artisan monero:status --all --method=pid
```

### Проверка конкретной ноды

```bash
# По ID ноды
php artisan monero:status 1

# По имени ноды
php artisan monero:status main-node

# С указанием метода
php artisan monero:status main-node --method=full
```

## Использование в коде

### Через фасад Monero

```php
use ItHealer\LaravelMonero\Facades\Monero;
use ItHealer\LaravelMonero\Models\MoneroNode;

// Получить ноду
$node = MoneroNode::find(1);

// Проверить статус (не сохраняет в БД)
$result = Monero::checkNodeStatus($node, 'api');
if ($result['status']) {
    echo "Процесс работает!";
    print_r($result['details']);
} else {
    echo "Процесс не работает: " . $result['details']['message'];
}

// Проверить и обновить статус в БД
$node = Monero::updateNodeStatus($node, 'api');
if ($node->worked) {
    echo "Процесс работает!";
}

// Проверить все ноды
$stats = Monero::checkAllNodesStatus('api');
echo "Работает: {$stats['working']}/{$stats['total']} нод";
```

### Через сервис ProcessHealthChecker

```php
use ItHealer\LaravelMonero\Services\ProcessHealthChecker;
use ItHealer\LaravelMonero\Models\MoneroNode;

$checker = app(ProcessHealthChecker::class);
$node = MoneroNode::find(1);

// Проверка по API
$result = $checker->checkByApi($node);

// Проверка по PID
$result = $checker->checkByPid($node);

// Проверка по порту
$result = $checker->checkByPort($node);

// Полная проверка
$result = $checker->checkFull($node);

// Результат всегда имеет структуру:
// [
//     'status' => bool,
//     'details' => [
//         'method' => string,
//         'message' => string,
//         // ... дополнительные поля в зависимости от метода
//     ]
// ]
```

### Получение данных из БД

```php
use ItHealer\LaravelMonero\Models\MoneroNode;

$node = MoneroNode::find(1);

// Проверяем статус
if ($node->worked) {
    echo "Процесс работает";
} else {
    echo "Процесс не работает";
}

// Получаем детальную информацию последней проверки
$details = $node->worked_data;
echo "Последняя проверка: " . $details['last_check'];
echo "Сообщение: " . $details['message'];
```

## Автоматический мониторинг

Процесс `php artisan monero` (SupervisorService) автоматически проверяет статус всех процессов каждые N секунд (настраивается через `monero.wallet_rpc.watcher_period`).

Статус процессов автоматически обновляется в полях:
- `worked` - булево значение (работает/не работает)
- `worked_data` - JSON с детальной информацией

### Пример структуры `worked_data`

При успешной работе:
```json
{
  "method": "api",
  "url": "http://127.0.0.1:18082/json_rpc",
  "version": 196613,
  "message": "API is responding",
  "last_check": "2025-01-15T10:30:45+00:00"
}
```

При ошибке:
```json
{
  "method": "api",
  "url": "http://127.0.0.1:18082/json_rpc",
  "error": "Connection refused",
  "message": "API check failed",
  "last_check": "2025-01-15T10:30:45+00:00"
}
```

## Настройка в supervisor

Пример конфигурации `/etc/supervisor/conf.d/monero.conf`:

```ini
[program:monero-worker]
command=php /path/to/your/project/artisan monero
directory=/path/to/your/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/supervisor/monero-worker.log
```

## Мониторинг через Cron (опционально)

Можно добавить дополнительную проверку через cron для отправки уведомлений:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $stats = Monero::checkAllNodesStatus('api');

        if ($stats['failed'] > 0) {
            // Отправить уведомление админу
            Log::error("Monero nodes check failed", $stats);
            // или
            Mail::to('admin@example.com')->send(new MoneroNodesDownMail($stats));
        }
    })->everyFiveMinutes();
}
```

## Рекомендации

1. **Для быстрых проверок в реальном времени** - используйте метод `api`
2. **Для массовой проверки всех нод** - также `api`, он оптимален по скорости и надежности
3. **Для диагностики проблем** - используйте метод `full` для получения полной картины
4. **Для проверки существования процесса** - `pid`, если не важна работоспособность API

## Примеры использования в контроллерах

```php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use ItHealer\LaravelMonero\Facades\Monero;
use ItHealer\LaravelMonero\Models\MoneroNode;

class MoneroNodeController extends Controller
{
    public function status(MoneroNode $node)
    {
        $result = Monero::checkNodeStatus($node, 'full');

        return response()->json($result);
    }

    public function statusAll()
    {
        $stats = Monero::checkAllNodesStatus('api');

        return response()->json($stats);
    }

    public function healthCheck()
    {
        // Простой health check endpoint
        $nodes = MoneroNode::where('available', true)
            ->where('worked', true)
            ->count();

        $total = MoneroNode::where('available', true)->count();

        if ($nodes === $total) {
            return response()->json(['status' => 'healthy'], 200);
        }

        return response()->json([
            'status' => 'degraded',
            'working' => $nodes,
            'total' => $total
        ], 503);
    }
}
```

## API маршруты

```php
// routes/api.php
Route::prefix('admin/monero')->group(function () {
    Route::get('/nodes/status', [MoneroNodeController::class, 'statusAll']);
    Route::get('/nodes/{node}/status', [MoneroNodeController::class, 'status']);
    Route::get('/health', [MoneroNodeController::class, 'healthCheck']);
});
```
