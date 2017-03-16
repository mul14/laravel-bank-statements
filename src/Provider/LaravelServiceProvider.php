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

class LaravelServiceProvider extends BankStatementsServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        parent::boot();

        $this->publishes([
            realpath(__DIR__.'/../../config/config.php') => config_path('sule/bank-statements.php')
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        parent::register();
    }
}
