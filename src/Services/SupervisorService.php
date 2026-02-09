<?php

namespace ItHealer\LaravelMonero\Services;

use ItHealer\LaravelMonero\Facades\Monero;
use ItHealer\LaravelMonero\Models\MoneroNode;
use Symfony\Component\Process\Process;

class SupervisorService extends BaseConsole
{
    protected bool $shouldRun = true;
    /** @var class-string<MoneroNode> */
    protected string $model = MoneroNode::class;
    protected array $processes = [];
    protected int $watcherPeriod;
    protected ProcessHealthChecker $healthChecker;

    public function __construct(ProcessHealthChecker $healthChecker)
    {
        $this->model = Monero::getModelNode();
        $this->watcherPeriod = (int)config('monero.wallet_rpc.watcher_period', 30);
        $this->healthChecker = $healthChecker;
    }

    protected function log(string $message, ?string $type = null): void
    {
        if ($this->logger) {
            call_user_func($this->logger, $message, $type);
        }
    }

    public function run(): void
    {
        parent::run();

        $this->log("Starting monero worker service...");

        $this
            ->sigterm()
            ->while()
            ->closeProcesses();

        $this->log("Monero worker stopped.");
    }

    protected function sigterm(): static
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->log("SIGTERM received. Shutting down gracefully...");
            $this->shouldRun = false;
        });

        pcntl_signal(SIGINT, function () {
            $this->log("SIGINT (Ctrl+C) received. Exiting...");
            $this->shouldRun = false;
        });

        return $this;
    }

    protected function while(): static
    {
        while ($this->shouldRun) {
            $this->thread();

            sleep($this->watcherPeriod);
        }

        return $this;
    }

    protected function thread(): void
    {
        $nodes = $this->model::query()
            ->where('available', true)
            ->whereNotNull('daemon')
            ->get();

        $activeNodesIDs = [];
        foreach( $nodes as $node ) {
            $activeNodesIDs[] = $node->id;

            // Проверяем статус существующего процесса
            if ($node->pid && isset($this->processes[$node->id])) {
                $this->updateProcessStatus($node);

                // Если процесс жив и работает, пропускаем запуск нового
                if ($this->processes[$node->id]->isRunning() && !$this->isPortFree($node->host, $node->port)) {
                    continue;
                }
            }

            if( !$this->isPortFree($node->host, $node->port) ) {
                continue;
            }

            if( $node->pid ) {
                $this->killPid($node->pid);
                $node->update(['pid' => null, 'worked' => false]);
            }

            $this->log("Starting monero-wallet-rpc for node {$node->name}...");
            try {
                $process = static::startProcess($node);
                $this->processes[$node->id] = $process;
                $node->update([
                    'pid' => $process->getPid(),
                    'worked' => true,
                    'worked_data' => [
                        'started_at' => now()->toIso8601String(),
                        'method' => 'supervisor',
                        'message' => 'Process started by supervisor',
                    ],
                ]);
                $this->log("Started process with PID {$node->pid} for node {$node->name}");
            }
            catch(\Exception $e) {
                $this->log("Error: {$e->getMessage()}", 'error');
                $node->update([
                    'worked' => false,
                    'worked_data' => [
                        'error' => $e->getMessage(),
                        'method' => 'supervisor',
                        'message' => 'Failed to start process',
                    ],
                ]);
            }
        }

        foreach ($this->processes as $nodeId => $process) {
            if (!in_array($nodeId, $activeNodesIDs)) {
                $this->log("Node #{$nodeId} no longer active, stopping process");
                $this->killProcess($nodeId);
            }
        }
    }

    public static function startProcess(MoneroNode $node): Process
    {
        $executePath = config('monero.wallet_rpc.execute_path') ?? 'monero-wallet-rpc';

        $args = [
            $executePath,
            '--wallet-dir', storage_path('app/monero'),
            '--rpc-bind-port', $node->port,
            '--daemon-address', $node->daemon,
            '--log-file', storage_path("logs/monero/$node->name.log"),
            '--non-interactive',
        ];
        if( $node->username ) {
            $args[] = '--rpc-login';
            $args[] = $node->username.':'.$node->password;
        }
        $process = new Process($args);
        $process->start();

        sleep(3);

        if( $error = $process->getErrorOutput() ) {
            if( $process->isRunning() ) {
                $process->stop();
            }
            throw new \Exception($error);
        }

        return $process;
    }

    protected function killProcess(int $nodeId): void
    {
        if (isset($this->processes[$nodeId])) {
            $process = $this->processes[$nodeId];

            if ($process->isRunning()) {
                $process->stop(3);
                $this->log("Stopped process for node #{$nodeId}");
            }

            unset($this->processes[$nodeId]);
        }

        $this->model::where('id', $nodeId)->update(['pid' => null]);
    }

    protected function closeProcesses(): static
    {
        foreach ($this->processes as $nodeId => $process) {
            $this->killProcess($nodeId);
        }

        return $this;
    }

    protected function isPortFree(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port);
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        return true;
    }

    /**
     * Обновляет статус процесса для ноды
     */
    protected function updateProcessStatus(MoneroNode $node): void
    {
        try {
            // Используем быструю проверку по API
            $result = $this->healthChecker->check($node, 'api');

            $node->update([
                'worked' => $result['status'],
                'worked_data' => array_merge(
                    $result['details'],
                    ['last_check' => now()->toIso8601String()]
                ),
            ]);

            if (!$result['status']) {
                $this->log("Node {$node->name} health check failed: {$result['details']['message']}", 'error');
            }
        } catch (\Exception $e) {
            $this->log("Failed to check node {$node->name} status: {$e->getMessage()}", 'error');
        }
    }

    protected function killPid(int $pid): void
    {
        if (posix_kill($pid, 0)) {
            exec("kill -9 {$pid}");
            $this->log("Killed process with PID {$pid}");
        }
        else {
            $this->log("Process with PID {$pid} is not killed", 'error');
        }
    }
}