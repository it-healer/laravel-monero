<?php

namespace ItHealer\LaravelMonero\Commands;

use Illuminate\Console\Command;
use ItHealer\LaravelMonero\Facades\Monero;
use ItHealer\LaravelMonero\Services\ProcessHealthChecker;

class MoneroStatusCommand extends Command
{
    protected $signature = 'monero:status
                            {node? : ID или имя ноды для проверки}
                            {--method=api : Метод проверки (pid, port, api, full)}
                            {--all : Проверить все ноды}';

    protected $description = 'Проверка статуса процессов monero-wallet-rpc';

    public function handle(ProcessHealthChecker $checker): int
    {
        $nodeIdentifier = $this->argument('node');
        $method = $this->option('method');
        $checkAll = $this->option('all');

        if ($checkAll || !$nodeIdentifier) {
            return $this->checkAllNodes($checker, $method);
        }

        return $this->checkSingleNode($checker, $nodeIdentifier, $method);
    }

    protected function checkAllNodes(ProcessHealthChecker $checker, string $method): int
    {
        $this->info("Проверка всех активных нод (метод: {$method})...\n");

        $stats = $checker->checkAllNodes($method);

        $this->table(
            ['ID', 'Имя', 'Статус', 'Сообщение'],
            collect($stats['nodes'])->map(fn($node) => [
                $node['id'],
                $node['name'],
                $node['status'] ? '<fg=green>✓ Работает</>' : '<fg=red>✗ Не работает</>',
                $node['message'],
            ])
        );

        $this->newLine();
        $this->info("Итого: {$stats['total']} нод");
        $this->info("<fg=green>Работают: {$stats['working']}</>");
        if ($stats['failed'] > 0) {
            $this->error("Не работают: {$stats['failed']}");
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function checkSingleNode(ProcessHealthChecker $checker, string $nodeIdentifier, string $method): int
    {
        $model = Monero::getModelNode();

        // Пробуем найти по ID или имени
        $node = is_numeric($nodeIdentifier)
            ? $model::find($nodeIdentifier)
            : $model::where('name', $nodeIdentifier)->first();

        if (!$node) {
            $this->error("Нода '{$nodeIdentifier}' не найдена");
            return self::FAILURE;
        }

        $this->info("Проверка ноды: {$node->name} (ID: {$node->id})");
        $this->info("Метод проверки: {$method}\n");

        $result = $checker->check($node, $method);

        // Обновляем статус в БД
        $node->update([
            'worked' => $result['status'],
            'worked_data' => $result['details'],
        ]);

        // Выводим результат
        if ($result['status']) {
            $this->info("✓ Процесс работает нормально");
        } else {
            $this->error("✗ Процесс не работает");
        }

        $this->newLine();
        $this->line("Детали проверки:");

        if ($method === 'full') {
            $this->displayFullCheckDetails($result['details']);
        } else {
            $this->displaySimpleDetails($result['details']);
        }

        return $result['status'] ? self::SUCCESS : self::FAILURE;
    }

    protected function displayFullCheckDetails(array $details): void
    {
        $checks = $details['checks'] ?? [];

        $this->table(
            ['Проверка', 'Статус', 'Информация'],
            [
                [
                    'PID',
                    $checks['pid']['status'] ? '<fg=green>✓</>' : '<fg=red>✗</>',
                    $checks['pid']['details']['message'] ?? 'N/A',
                ],
                [
                    'Порт',
                    $checks['port']['status'] ? '<fg=green>✓</>' : '<fg=red>✗</>',
                    $checks['port']['details']['message'] ?? 'N/A',
                ],
                [
                    'API',
                    $checks['api']['status'] ? '<fg=green>✓</>' : '<fg=red>✗</>',
                    $checks['api']['details']['message'] ?? 'N/A',
                ],
            ]
        );

        if (isset($checks['pid']['details']['pid'])) {
            $this->line("PID: {$checks['pid']['details']['pid']}");
        }

        if (isset($checks['api']['details']['version'])) {
            $this->line("Версия: {$checks['api']['details']['version']}");
        }
    }

    protected function displaySimpleDetails(array $details): void
    {
        foreach ($details as $key => $value) {
            if (is_scalar($value)) {
                $this->line("  {$key}: {$value}");
            }
        }
    }
}
