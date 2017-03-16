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

use Carbon\Carbon;

interface WebInterface
{
    /**
     * Return the HTTP Client.
     *
     * @return \GuzzleHttp\ClientInterface
     */
    public function client();

    /**
     * Return the HTTP Cookie Jar.
     *
     * @return \GuzzleHttp\Cookie\CookieJarInterface
     */
    public function getCookieJar();

    /**
     * Return default HTTP request options.
     *
     * @return Array
     */
    public function getRequestOptions();

    /**
     * Set the temporary storage path.
     *
     * @param  string  $path
     * @return void
     */
    public function setTempStoragePath($path);

    /**
     * Set the base URL.
     *
     * @param  string  $uri
     * @return void
     */
    public function setBaseUri($uri);

    /**
     * Set the base URL.
     *
     * @param  string  $userId
     * @param  string  $password
     * @return void
     */
    public function setCredential($userId, $password);

    /**
     * Set additional entity params.
     *
     * @param  array  $params
     * @return void
     */
    public function setAdditionalEntityParams(Array $params);
    
    /**
     * The landing page.
     *
     * @return int
     * @throws \RuntimeException
     */
    public function landing();

    /**
     * Do login.
     *
     * @return int
     * @throws \RuntimeException
     */
    public function login();

    /**
     * Save state for later use.
     *
     * @param  string  $identifier
     * @return bool
     */
    public function saveState($identifier);

    /**
     * Restore state saved earlier.
     *
     * @param  string  $identifier
     * @param  bool    $removeTempStorage
     * @return bool
     */
    public function restoreState($identifier, $removeTempStorage = true);

    /**
     * Do collect.
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     * @return int
     * @throws \RuntimeException
     */
    public function collect(Carbon $startDate, Carbon $endDate);

    /**
     * Do logout.
     *
     * @return int
     * @throws \RuntimeException
     */
    public function logout();
}
