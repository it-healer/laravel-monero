<?php

namespace ItHealer\LaravelMonero;

use ItHealer\LaravelMonero\Commands\MoneroCommand;
use ItHealer\LaravelMonero\Commands\MoneroNodeSyncCommand;
use ItHealer\LaravelMonero\Commands\MoneroSyncCommand;
use ItHealer\LaravelMonero\Commands\MoneroWalletRPCCommand;
use ItHealer\LaravelMonero\Commands\MoneroWalletSyncCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MoneroServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('monero')
            ->hasConfigFile()
            ->discoversMigrations()
            ->hasCommands([
                MoneroCommand::class,
                MoneroSyncCommand::class,
                MoneroWalletSyncCommand::class,
                MoneroWalletRPCCommand::class,
                MoneroNodeSyncCommand::class,
            ])
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('it-healer/laravel-monero');
            });

        $this->app->singleton(Monero::class);
    }
}