<?php

namespace ItHealer\LaravelMonero\Services;

use ItHealer\LaravelMonero\Models\MoneroNode;
use Illuminate\Support\Facades\Http;
use Exception;

class ProcessHealthChecker
{
    /**
     * Проверяет работоспособность процесса monero-wallet-rpc для ноды
     *
     * @param MoneroNode $node
     * @param string $method Метод проверки: 'pid', 'port', 'api', 'full'
     * @return array ['status' => bool, 'details' => array]
     */
    public function check(MoneroNode $node, string $method = 'full'): array
    {
        return match($method) {
            'pid' => $this->checkByPid($node),
            'port' => $this->checkByPort($node),
            'api' => $this->checkByApi($node),
            'full' => $this->checkFull($node),
            default => throw new \InvalidArgumentException("Unknown check method: {$method}"),
        };
    }

    /**
     * Проверка существования процесса по PID
     * Быстрая проверка, но PID может быть переиспользован другим процессом
     */
    public function checkByPid(MoneroNode $node): array
    {
        if (!$node->pid) {
            return [
                'status' => false,
                'details' => [
                    'method' => 'pid',
                    'message' => 'PID not set',
                ],
            ];
        }

        // Проверяем существование процесса
        if (!posix_kill($node->pid, 0)) {
            return [
                'status' => false,
                'details' => [
                    'method' => 'pid',
                    'pid' => $node->pid,
                    'message' => 'Process not found',
                ],
            ];
        }

        // Дополнительно проверяем, что это действительно monero-wallet-rpc
        $cmdline = $this->getProcessCmdline($node->pid);
        $isMoneroProcess = $cmdline && str_contains($cmdline, 'monero-wallet-rpc');

        return [
            'status' => $isMoneroProcess,
            'details' => [
                'method' => 'pid',
                'pid' => $node->pid,
                'cmdline' => $cmdline,
                'is_monero_process' => $isMoneroProcess,
                'message' => $isMoneroProcess ? 'Process is running' : 'Process exists but not monero-wallet-rpc',
            ],
        ];
    }

    /**
     * Проверка занятости порта
     * Быстрая проверка, но порт может быть занят другим процессом
     */
    public function checkByPort(MoneroNode $node): array
    {
        $connection = @fsockopen($node->host, $node->port, $errno, $errstr, 1);

        if (is_resource($connection)) {
            fclose($connection);
            return [
                'status' => true,
                'details' => [
                    'method' => 'port',
                    'host' => $node->host,
                    'port' => $node->port,
                    'message' => 'Port is in use',
                ],
            ];
        }

        return [
            'status' => false,
            'details' => [
                'method' => 'port',
                'host' => $node->host,
                'port' => $node->port,
                'error' => "{$errno}: {$errstr}",
                'message' => 'Port is not in use',
            ],
        ];
    }

    /**
     * Проверка через RPC API
     * Самая надежная проверка - действительно ли процесс отвечает
     */
    public function checkByApi(MoneroNode $node): array
    {
        try {
            $url = "http://{$node->host}:{$node->port}/json_rpc";

            $request = Http::timeout(3);

            if ($node->username && $node->password) {
                $request->withBasicAuth($node->username, $node->password);
            }

            $response = $request->post($url, [
                'jsonrpc' => '2.0',
                'id' => '0',
                'method' => 'get_version',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => true,
                    'details' => [
                        'method' => 'api',
                        'url' => $url,
                        'version' => $data['result']['version'] ?? null,
                        'message' => 'API is responding',
                    ],
                ];
            }

            return [
                'status' => false,
                'details' => [
                    'method' => 'api',
                    'url' => $url,
                    'status_code' => $response->status(),
                    'message' => 'API returned error',
                ],
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'details' => [
                    'method' => 'api',
                    'url' => $url ?? null,
                    'error' => $e->getMessage(),
                    'message' => 'API check failed',
                ],
            ];
        }
    }

    /**
     * Полная проверка всеми методами
     * Возвращает детальную информацию о состоянии процесса
     */
    public function checkFull(MoneroNode $node): array
    {
        $pidCheck = $this->checkByPid($node);
        $portCheck = $this->checkByPort($node);
        $apiCheck = $this->checkByApi($node);

        // Процесс считается рабочим, если API отвечает
        $isWorking = $apiCheck['status'];

        // Собираем детальную информацию
        $details = [
            'method' => 'full',
            'checks' => [
                'pid' => $pidCheck,
                'port' => $portCheck,
                'api' => $apiCheck,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        // Определяем статус и сообщение
        if ($isWorking) {
            $message = 'Process is fully operational';
        } elseif ($portCheck['status'] && !$apiCheck['status']) {
            $message = 'Port is occupied but API is not responding';
        } elseif ($pidCheck['status'] && !$portCheck['status']) {
            $message = 'Process exists but port is not in use';
        } else {
            $message = 'Process is not running';
        }

        $details['message'] = $message;
        $details['summary'] = [
            'pid_alive' => $pidCheck['status'],
            'port_in_use' => $portCheck['status'],
            'api_responding' => $apiCheck['status'],
        ];

        return [
            'status' => $isWorking,
            'details' => $details,
        ];
    }

    /**
     * Обновляет статус ноды в базе данных
     */
    public function updateNodeStatus(MoneroNode $node, string $method = 'full'): MoneroNode
    {
        $result = $this->check($node, $method);

        $node->update([
            'worked' => $result['status'],
            'worked_data' => $result['details'],
        ]);

        return $node->fresh();
    }

    /**
     * Проверяет все активные ноды и обновляет их статусы
     *
     * @return array Статистика проверки
     */
    public function checkAllNodes(string $method = 'api'): array
    {
        $nodes = MoneroNode::query()
            ->where('available', true)
            ->whereNotNull('daemon')
            ->get();

        $stats = [
            'total' => $nodes->count(),
            'working' => 0,
            'failed' => 0,
            'nodes' => [],
        ];

        foreach ($nodes as $node) {
            $result = $this->check($node, $method);

            $node->update([
                'worked' => $result['status'],
                'worked_data' => $result['details'],
            ]);

            if ($result['status']) {
                $stats['working']++;
            } else {
                $stats['failed']++;
            }

            $stats['nodes'][] = [
                'id' => $node->id,
                'name' => $node->name,
                'status' => $result['status'],
                'message' => $result['details']['message'] ?? 'Unknown',
            ];
        }

        return $stats;
    }

    /**
     * Получает командную строку процесса по PID (безопасно)
     */
    protected function getProcessCmdline(int $pid): ?string
    {
        // Валидируем, что PID - это действительно число
        if (!is_numeric($pid) || $pid <= 0) {
            return null;
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Используем escapeshellarg для безопасности
                $escapedPid = escapeshellarg((string)$pid);
                $output = shell_exec("wmic process where ProcessId={$escapedPid} get CommandLine 2>&1");
                return $output ? trim(preg_replace('/CommandLine\s+/', '', $output)) : null;
            } else {
                // Linux/Mac - читаем напрямую из /proc (безопаснее)
                $cmdlinePath = "/proc/{$pid}/cmdline";
                if (file_exists($cmdlinePath)) {
                    $content = @file_get_contents($cmdlinePath);
                    return $content ? str_replace("\0", ' ', $content) : null;
                }

                // Fallback для Mac - используем escapeshellarg
                $escapedPid = escapeshellarg((string)$pid);
                $output = shell_exec("ps -p {$escapedPid} -o command= 2>&1");
                return $output ? trim($output) : null;
            }
        } catch (Exception $e) {
            return null;
        }
    }
}
