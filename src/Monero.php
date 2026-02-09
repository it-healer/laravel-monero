<?php

namespace ItHealer\LaravelMonero;

use Illuminate\Support\Facades\Cache;
use ItHealer\LaravelMonero\Concerns\Accounts;
use ItHealer\LaravelMonero\Concerns\Addresses;
use ItHealer\LaravelMonero\Concerns\Nodes;
use ItHealer\LaravelMonero\Concerns\Transfers;
use ItHealer\LaravelMonero\Concerns\Wallets;
use ItHealer\LaravelMonero\Models\MoneroAccount;
use ItHealer\LaravelMonero\Models\MoneroDeposit;
use ItHealer\LaravelMonero\Models\MoneroNode;
use ItHealer\LaravelMonero\Models\MoneroTransaction;
use ItHealer\LaravelMonero\Models\MoneroWallet;
use ItHealer\LaravelMonero\WebhookHandlers\WebhookHandlerInterface;

class Monero
{
    use Nodes, Wallets, Accounts, Addresses, Transfers;

    /**
     * @return class-string<MoneroNode>
     */
    public function getModelNode(): string
    {
        return config('monero.models.node');
    }

    /**
     * @return class-string<MoneroWallet>
     */
    public function getModelWallet(): string
    {
        return config('monero.models.wallet');
    }

    /**
     * @return class-string<MoneroAccount>
     */
    public function getModelAccount(): string
    {
        return config('monero.models.account');
    }

    /**
     * @return class-string<MoneroAccount>
     */
    public function getModelAddress(): string
    {
        return config('monero.models.address');
    }

    /**
     * @return class-string<MoneroDeposit>
     */
    public function getModelDeposit(): string
    {
        return config('monero.models.deposit');
    }

    /**
     * @return class-string<WebhookHandlerInterface>
     */
    public function getModelWebhook(): string
    {
        return config('monero.webhook_handler');
    }

    /**
     * @return class-string<MoneroTransaction>
     */
    public function getModelTransaction(): string
    {
        return config('monero.models.transaction');
    }

    public function atomicLock(string $name, ?callable $callback, ?int $wait = null): mixed
    {
        $lockName = config('monero.atomic_lock.prefix').'_'.$name;
        $lockTimeout = (int)config('monero.atomic_lock.timeout', 300);
        $wait = $wait ?? (int)config('monero.atomic_lock.wait', 15);

        return Cache::lock($lockName, $lockTimeout)->block($wait, $callback);
    }

    public function nodeAtomicLock(MoneroNode $node, ?callable $callback, ?int $wait = null): mixed
    {
        if( $node->isLocal() ) {
            return call_user_func($callback);
        }

        return $this->atomicLock('node_'.$node->id, $callback, $wait);
    }

    public function walletAtomicLock(MoneroWallet $wallet, ?callable $callback, ?int $wait = null): mixed
    {
        if( $wallet->node->isLocal() ) {
            return call_user_func($callback);
        }

        return $this->atomicLock('wallet_'.$wallet->id, $callback, $wait);
    }

    public function generalAtomicLock(MoneroWallet $wallet, ?callable $callback, ?int $wait = null): mixed
    {
        return $this->nodeAtomicLock($wallet->node, fn() => $this->walletAtomicLock($wallet, $callback, $wait), $wait);
    }

    /**
     * Проверяет статус процесса monero-wallet-rpc для ноды
     *
     * @param MoneroNode $node
     * @param string $method Метод проверки: 'pid', 'port', 'api', 'full'
     * @return array ['status' => bool, 'details' => array]
     */
    public function checkNodeStatus(MoneroNode $node, string $method = 'api'): array
    {
        return app(\ItHealer\LaravelMonero\Services\ProcessHealthChecker::class)
            ->check($node, $method);
    }

    /**
     * Проверяет и обновляет статус ноды в базе данных
     *
     * @param MoneroNode $node
     * @param string $method Метод проверки: 'pid', 'port', 'api', 'full'
     * @return MoneroNode
     */
    public function updateNodeStatus(MoneroNode $node, string $method = 'api'): MoneroNode
    {
        return app(\ItHealer\LaravelMonero\Services\ProcessHealthChecker::class)
            ->updateNodeStatus($node, $method);
    }

    /**
     * Проверяет все активные ноды и обновляет их статусы
     *
     * @param string $method Метод проверки
     * @return array Статистика проверки
     */
    public function checkAllNodesStatus(string $method = 'api'): array
    {
        return app(\ItHealer\LaravelMonero\Services\ProcessHealthChecker::class)
            ->checkAllNodes($method);
    }
}
