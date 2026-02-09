# Сводка: Мониторинг процессов monero-wallet-rpc

## Что реализовано

### 1. Сервис ProcessHealthChecker
**Файл:** `src/Services/ProcessHealthChecker.php`

Предоставляет 4 метода проверки статуса процесса:
- `checkByPid()` - проверка по PID процесса
- `checkByPort()` - проверка занятости порта
- `checkByApi()` - **рекомендуется** - проверка через RPC API
- `checkFull()` - полная проверка всеми методами

Дополнительные методы:
- `updateNodeStatus()` - проверяет и обновляет статус в БД
- `checkAllNodes()` - массовая проверка всех нод

### 2. Artisan команда
**Файл:** `src/Commands/MoneroStatusCommand.php`

```bash
php artisan monero:status --all              # Проверить все ноды
php artisan monero:status 1                  # Проверить ноду по ID
php artisan monero:status main-node          # Проверить ноду по имени
php artisan monero:status 1 --method=full    # Полная диагностика
```

### 3. Интеграция в SupervisorService
**Файл:** `src/Services/SupervisorService.php`

Добавлены:
- Автоматическое обновление статусов процессов
- Сохранение детальной информации в поля `worked` и `worked_data`
- Метод `updateProcessStatus()` для периодических проверок

### 4. Методы в фасаде Monero
**Файл:** `src/Monero.php`

```php
// Проверить статус (без сохранения)
Monero::checkNodeStatus($node, 'api');

// Проверить и сохранить статус
Monero::updateNodeStatus($node, 'api');

// Проверить все ноды
Monero::checkAllNodesStatus('api');
```

### 5. Документация
- `PROCESS_MONITORING.md` - полная документация
- `EXAMPLES_STATUS_CHECK.md` - быстрые примеры

## Структура данных

### Таблица `monero_nodes`
Используются поля:
- `pid` (integer) - PID процесса
- `worked` (boolean) - статус работы процесса
- `worked_data` (json) - детальная информация о статусе

### Пример `worked_data` при успехе:
```json
{
  "method": "api",
  "url": "http://127.0.0.1:18082/json_rpc",
  "version": 196613,
  "message": "API is responding",
  "last_check": "2025-01-15T10:30:45+00:00"
}
```

### Пример `worked_data` при ошибке:
```json
{
  "method": "api",
  "url": "http://127.0.0.1:18082/json_rpc",
  "error": "Connection refused",
  "message": "API check failed",
  "last_check": "2025-01-15T10:30:45+00:00"
}
```

## Рекомендуемые сценарии использования

### 1. Production мониторинг (автоматический)
SupervisorService автоматически проверяет все процессы каждые N секунд (настраивается через `monero.wallet_rpc.watcher_period`).

### 2. Проверка статуса перед операциями
```php
$node = MoneroNode::find(1);
$result = Monero::checkNodeStatus($node, 'api');

if (!$result['status']) {
    throw new Exception("Node is not available: {$result['details']['message']}");
}

// Выполнить операцию с нодой
```

### 3. Health check endpoint
```php
Route::get('/api/monero/health', function () {
    $stats = Monero::checkAllNodesStatus('api');
    return response()->json([
        'status' => $stats['failed'] === 0 ? 'healthy' : 'degraded',
        'working' => $stats['working'],
        'total' => $stats['total'],
    ], $stats['failed'] === 0 ? 200 : 503);
});
```

### 4. Мониторинг через Cron
```php
$schedule->call(function () {
    $stats = Monero::checkAllNodesStatus('api');
    if ($stats['failed'] > 0) {
        Mail::to('admin@example.com')->send(new MoneroNodesDownMail($stats));
    }
})->everyFiveMinutes();
```

### 5. Административная панель
```php
public function dashboard()
{
    $nodes = MoneroNode::where('available', true)->get();

    $stats = [
        'total' => $nodes->count(),
        'working' => $nodes->where('worked', true)->count(),
        'failed' => $nodes->where('worked', false)->count(),
    ];

    return view('admin.monero.dashboard', compact('nodes', 'stats'));
}
```

## Сравнение методов проверки

| Метод | Скорость | Надежность | Использование |
|-------|----------|------------|---------------|
| **pid** | Очень быстро (< 1ms) | Низкая - PID может быть переиспользован | Быстрая проверка существования |
| **port** | Быстро (< 10ms) | Средняя - порт может быть занят другим процессом | Проверка доступности порта |
| **api** | Средне (50-200ms) | **Высокая** - проверяет реальную работоспособность | **⭐ Рекомендуется для production** |
| **full** | Медленно (50-300ms) | Высокая + полная диагностика | Отладка и диагностика проблем |

## Что дальше?

Возможные улучшения:
1. ✅ Уведомления при падении процессов (через Laravel Notifications)
2. ✅ Dashboard для мониторинга (Livewire или Vue.js)
3. ✅ Метрики и статистика (интеграция с Prometheus/Grafana)
4. ✅ Автоматический перезапуск упавших процессов (уже есть в SupervisorService)
5. ✅ История статусов (создать таблицу `monero_node_status_history`)

## Тестирование

```bash
# 1. Запустить supervisor
php artisan monero

# 2. В другом терминале проверить статус
php artisan monero:status --all

# 3. Остановить процесс вручную (для теста)
kill -9 <PID>

# 4. Проверить, что статус обновился
php artisan monero:status --all

# 5. Через ~30 секунд supervisor автоматически перезапустит процесс
```

## Безопасность

- Все команды защищены от command injection через `escapeshellarg()`
- PID валидируется перед использованием
- Используется `posix_kill($pid, 0)` для безопасной проверки процесса
- Чтение `/proc/{pid}/cmdline` на Linux вместо shell команд где возможно
