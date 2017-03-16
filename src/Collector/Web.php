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

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;

use DOMNode;

use Ramsey\Uuid\Uuid;

use RuntimeException;

abstract class Web implements WebInterface
{
    /**
     * The HTTP Client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * The HTTP Cookie Jar.
     *
     * @var \GuzzleHttp\Cookie\CookieJarInterface
     */
    protected $cookieJar;

    /**
     * The connection user agent.
     *
     * @var string
     */
    protected $userAgent;

    /**
     * The connection IP Address.
     *
     * @var string
     */
    protected $ipAddress;

    /**
     * The request delay.
     *
     * @var int
     */
    protected $requestDelay;

    /**
     * The connection options.
     *
     * @var array
     */
    protected $options;

    /**
     * The temporarly storage path.
     *
     * @var string
     */
    protected $tempStoragePath;

    /**
     * Does we already open the landing page.
     *
     * @var bool
     */
    protected $isLanded = false;

    /**
     * The base uri.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * The user ID.
     *
     * @var string
     */
    protected $userId;

    /**
     * The user password.
     *
     * @var string
     */
    protected $password;

    /**
     * The additional entity params.
     *
     * @var array
     */
    protected $additionalEntityParams = [];

    /**
     * The login uri.
     *
     * @var string
     */
    protected $loginUri;

    /**
     * Create a new instance.
     *
     * @param  array   $config
     * @param  string  $userAgent
     * @param  string  $ipAddress
     * @param  array   $options
     * @return void
     * @throws \RuntimeException
     */
    public function __construct(Array $config)
    {
        if ( ! isset($config['user_agent'])) {
            throw new RuntimeException('No user_agent config defined');
        }

        if ( ! isset($config['ip_address'])) {
            throw new RuntimeException('No ip_address config defined');
        }

        if ( ! isset($config['request_delay'])) {
            throw new RuntimeException('No request_delay config defined');
        }

        if ( ! isset($config['options'])) {
            throw new RuntimeException('No options config defined');
        }

        $this->userAgent    = $config['user_agent'];
        $this->ipAddress    = $config['ip_address'];
        $this->requestDelay = $config['request_delay'];
        $this->options      = $config['options'];
    }

    /**
     * {@inheritdoc}
     */
    public function client()
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = new Client();
        }

        return $this->httpClient;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieJar()
    {
        if (is_null($this->cookieJar)) {
            $this->cookieJar = new CookieJar();
        }

        return $this->cookieJar;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestOptions()
    {
        return array_merge($this->options, [
            'verify'  => false,
            'cookies' => $this->getCookieJar(), 
            'headers' => [
                'User-Agent'      => $this->userAgent, 
                'X-Forwarded-For' => $this->ipAddress
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function setTempStoragePath($path)
    {
        $this->tempStoragePath = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseUri($uri)
    {
        $this->baseUri = rtrim($uri, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function setCredential($userId, $password)
    {
        $this->userId   = $userId;
        $this->password = $password;
    }

    /**
     * {@inheritdoc}
     */
    public function setAdditionalEntityParams(Array $params)
    {
        $this->additionalEntityParams = $params;
    }

    /**
     * {@inheritdoc}
     */
    public function login()
    {
        if ( ! $this->isLanded) {
            throw new RuntimeException('Use ->landing() method first');
        }

        if (is_null($this->loginUri)) {
            throw new RuntimeException('No login uri defined');
        }

        if (is_null($this->userId)) {
            throw new RuntimeException('No User ID defined');
        }

        if (is_null($this->password)) {
            throw new RuntimeException('No User Password defined');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveState($identifier)
    {
        $this->saveCookies($identifier);

        $filePath = $this->getStateDataFilePath($identifier);

        file_put_contents($filePath, serialize($this->getImportantStateData()));

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function restoreState($identifier, $removeTempStorage = true)
    {
        $this->restoreCookies($identifier, $removeTempStorage);

        $filePath = $this->getStateDataFilePath($identifier);

        if (file_exists($filePath)) {
            $this->setImportantStateData(unserialize(file_get_contents($filePath)));

            if ($removeTempStorage) {
                $this->removeStateStorage($filePath);
            }

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getImportantStateData()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function setImportantStateData(Array $data)
    {}

    /**
     * Save cookies for later use.
     *
     * @param  string  $identifier
     * @return void
     */
    protected function saveCookies($identifier)
    {
        $filePath = $this->getStateCookiesFilePath($identifier);

        $cookies = $this->getCookieJar()->getIterator();

        $cookiesTxt = '';
        foreach ($cookies as $cookie) {
            $cookiesTxt .= serialize($cookie->toArray())."\n";
        }

        file_put_contents($filePath, $cookiesTxt);
    }

    /**
     * Restore cookies saved earlier.
     *
     * @param  string  $identifier
     * @param  bool    $removeTempStorage
     * @return void
     */
    protected function restoreCookies($identifier, $removeTempStorage = true)
    {
        $filePath = $this->getStateCookiesFilePath($identifier);

        if (file_exists($filePath)) {
            $cookiesTxt = file_get_contents($filePath);
            $cookiesTxt = explode("\n", $cookiesTxt);

            foreach ($cookiesTxt as $item) {
                if (empty($item)) {
                    continue;
                }

                $this->getCookieJar()->setCookie(new SetCookie(unserialize($item)));
            }

            if ($removeTempStorage) {
                $this->removeStateStorage($filePath);
            }
        }
    }

    /**
     * Remove state data storage.
     *
     * @param  string  $filePath
     * @return void
     */
    protected function removeStateStorage($filePath)
    {
        @unlink($filePath);
    }

    /**
     * Return cookie file path.
     *
     * @param  string  $identifier
     * @return string
     * @throws \RuntimeException
     */
    protected function getStateCookiesFilePath($identifier)
    {
        if (is_null($this->tempStoragePath)) {
            throw new RuntimeException('Temporary storage file path is required');
        }

        return $this->tempStoragePath.'/'.$identifier.'-cookies.txt';
    }

    /**
     * Return data file path.
     *
     * @param  string  $identifier
     * @return string
     * @throws \RuntimeException
     */
    protected function getStateDataFilePath($identifier)
    {
        if (is_null($this->tempStoragePath)) {
            throw new RuntimeException('Temporary storage file path is required');
        }

        return $this->tempStoragePath.'/'.$identifier.'-data.txt';
    }

    /**
     * Return a data unique ID.
     *
     * @param  string  $data
     * @return string
     */
    protected function generateIdentifier($data)
    {
        return Uuid::uuid5(Uuid::NAMESPACE_DNS, $data);
    }

    /**
     * Extract inner HTML from a DOM Node.
     *
     * @param  \DOMNode  $node
     * @return string
     */
    protected function DOMinnerHTML(DOMNode $node) 
    { 
        $str = ''; 
        $children = $node->childNodes;

        foreach ($children as $child) { 
            $str .= $node->ownerDocument->saveHTML($child);
        }

        return $str; 
    }
}
