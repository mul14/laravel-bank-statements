<?php

namespace Sule\BankStatements\Provider;

/*
 * This file is part of the Sulaeman Bank Statements package.
 *
 * (c) Sulaeman <me@sulaeman.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\ServiceProvider;

use Sule\BankStatements\Console\TableCommand;
use Sule\BankStatements\Console\AccountsTableCommand;

use Sule\BankStatements\Account;
use Sule\BankStatements\Statement;
use Sule\BankStatements\NullProvider;

use RuntimeException;

abstract class BankStatementsServiceProvider extends ServiceProvider
{
    /**
     * The collector classes.
     *
     * @var array
     */
    protected $collectorClasses;

    /**
     * The collector instances.
     *
     * @var array
     */
    protected $collectors;

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TableCommand::class,
                AccountsTableCommand::class
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $this->setupConfig();

        $this->registerAccountServices();
        $this->registerStatementServices();

        $this->registerCollectorServices();
    }

    /**
     * Setup the configuration.
     *
     * @return void
     */
    protected function setupConfig()
    {
        $this->mergeConfigFrom(realpath(__DIR__.'/../../config/config.php'), 'sule/bank-statements');
    }

    /**
     * Register the account services.
     *
     * @return void
     */
    protected function registerAccountServices()
    {
        $this->app->singleton(Account::class, function ($app) {
            $config = $app['config']['sule/bank-statements.accounts'];

            return isset($config['table'])
                        ? new Account(
                            $app['db'], $config['database'], $config['table']
                        ) : new NullProvider;
        });
    }

    /**
     * Register the statement services.
     *
     * @return void
     */
    protected function registerStatementServices()
    {
        $this->app->singleton(Statement::class, function ($app) {
            $config = $app['config']['sule/bank-statements.statements'];
            $tempStoragePath = $app['config']['sule/bank-statements.collector.temp_storage_path'];

            $collectorClasses   = $this->getCollectorClasses();
            $collectorInstances = $this->getCollectors();
            $collectors = [];
            foreach ($collectorClasses as $index => $class) {
                $collectors[$index] = $collectorInstances[$class];
            }

            return isset($config['table'])
                        ? new Statement(
                            $app['db'], $config['database'], $config['table'], $app[Account::class], $collectors, $tempStoragePath
                        ) : new NullProvider;
        });
    }

    /**
     * Register the collector services.
     *
     * @return void
     */
    protected function registerCollectorServices()
    {
        $config = $this->app['config']['sule/bank-statements.client'];

        foreach ($this->getCollectors() as $index => $item) {
            $this->app->singleton($index, function () use ($item) {
                return $item;
            });
        }
    }

    /**
     * Return collector instances available.
     *
     * @return array
     * @throws \RuntimeException
     */
    protected function getCollectors()
    {
        if (is_null($this->collectors)) {
            $config = $this->app['config']['sule/bank-statements.client'];

            $this->collectors = [];

            foreach ($this->getCollectorClasses() as $item) {
                $this->collectors[$item] = new $item($config);
            }
        }

        return $this->collectors;
    }

    /**
     * Return collector classes available.
     *
     * @return array
     * @throws \RuntimeException
     */
    protected function getCollectorClasses()
    {
        if (is_null($this->collectorClasses)) {
            $config = $this->app['config']['sule/bank-statements.collector'];
            $type   = $config['type'];

            if ( ! isset($config[$type])) {
                throw new RuntimeException('No collector type available');
            }

            if (empty($config[$type])) {
                throw new RuntimeException('No collectors available');
            }

            $this->collectorClasses = $config[$type];
        }

        return $this->collectorClasses;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_merge(
            [Account::class, Statement::class], 
            array_values($this->getCollectorClasses())
        );
    }
}
