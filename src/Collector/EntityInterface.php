<?php

namespace Sule\BankStatements\Collector;

/*
 * This file is part of the Sulaeman Bank Statements package.
 *
 * (c) Sulaeman <me@sulaeman.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface EntityInterface
{
    /**
     * This is used to fetch readonly variables, you can not read the registry
     * instance reference through here.
     * 
     * @param string $var
     * @return bool|string|array
     */
    public function __get($var);
}
