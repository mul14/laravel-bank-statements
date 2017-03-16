<?php

namespace Sule\BankStatements\Collector\Web;

/*
 * This file is part of the Sulaeman Bank Statements package.
 *
 * (c) Sulaeman <me@sulaeman.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Sule\BankStatements\Collector\Web;

use Illuminate\Support\Collection;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Psr7;

use Carbon\Carbon;

use DOMDocument;
use DOMNodeList;
use DOMNode;

use Sule\BankStatements\Collector\Entity;

use RuntimeException;
use Sule\BankStatements\LoginFailureException;

class Bca extends Web
{
    /**
     * Does we already logged in.
     *
     * @var bool
     */
    protected $isLoggedIn = false;

    /**
     * The effective uri.
     *
     * @var string
     */
    public $effectiveUri;

    /**
     * {@inheritdoc}
     */
    public function landing()
    {
        if (is_null($this->baseUri)) {
            throw new RuntimeException('No base uri defined');
        }
        
        $options = $this->getRequestOptions();
        $options['headers'] = array_merge($options['headers'], [
            'Referer' => $this->baseUri
        ]);
        $options['on_stats'] = function (TransferStats $stats) {
            $this->effectiveUri = $stats->getEffectiveUri();
        };

        try {
            $response = $this->client()->request('GET', $this->baseUri, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException(Psr7\str($e->getResponse()));
            } else {
                throw new RuntimeException($e->getMessage());
            }
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode == 200) {
            $body = (string) $response->getBody();
            $body = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $body);

            $dom = new DOMDocument('1.0', 'UTF-8');

            // set error level
            $internalErrors = libxml_use_internal_errors(true);
            
            $dom->recover = true;
            $dom->strictErrorChecking = false;
            $dom->loadHTML($body);

            // Restore error level
            libxml_use_internal_errors($internalErrors);

            $forms = $dom->getElementsByTagName('form');

            if ($forms instanceOf DOMNodeList) {
                foreach ($forms as $form) {
                    if ($form->getAttribute('name') == 'iBankForm') {
                        $this->loginUri = $this->baseUri.'/'.ltrim($form->getAttribute('action'), '/');
                        break;
                    }
                }
            }

            $this->isLanded = true;
        }

        return $statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function login()
    {
        parent::login();

        sleep($this->requestDelay);

        $options = $this->getRequestOptions();
        $options['headers'] = array_merge($options['headers'], [
            'Referer' => $this->effectiveUri
        ]);
        $options['on_stats'] = function (TransferStats $stats) {
            $this->effectiveUri = $stats->getEffectiveUri();
        };
        $options['form_params'] = [
            'value(actions)'      => 'login', 
            'value(user_id)'      => $this->userId, 
            'value(user_ip)'      => $this->ipAddress, 
            'value(browser_info)' => $this->userAgent, 
            'value(mobile)'       => 'false', 
            'value(pswd)'         => $this->password, 
            'value(Submit)'       => 'LOGIN'
        ];

        try {
            $response = $this->client()->request('POST', $this->loginUri, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException(Psr7\str($e->getResponse()));
            } else {
                throw new RuntimeException($e->getMessage());
            }
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode == 200) {
            $body = (string) $response->getBody();
            $body = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $body);
            
            $dom = new DOMDocument('1.0', 'UTF-8');

            // set error level
            $internalErrors = libxml_use_internal_errors(true);
            
            $dom->recover = true;
            $dom->strictErrorChecking = false;
            $dom->loadHTML($body);

            // Restore error level
            libxml_use_internal_errors($internalErrors);

            $inputs = $dom->getElementsByTagName('input');

            if ($inputs instanceOf DOMNodeList) {
                foreach ($inputs as $input) {
                    if ($input->getAttribute('name') == 'value(Submit)') {
                        throw new LoginFailureException('Failed to login, maybe you already logged in previously while not yet logged out');
                    }
                }
            }

            $this->isLoggedIn = true;
        }

        return $statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Carbon $startDate, Carbon $endDate)
    {
        if ( ! $this->isLoggedIn) {
            throw new RuntimeException('Use ->login() method first');
        }

        if ($startDate->month != $endDate->month || $startDate->year != $endDate->year) {
            $this->logout();

            throw new RuntimeException('Unable to collect in different month / year');
        }

        if ($this->openMenu() != 200) {
            $this->logout();

            throw new RuntimeException('Unable to open the menu content');
        }

        if ($this->openAccountInformationMenu() != 200) {
            $this->logout();

            throw new RuntimeException('Unable to open the menu content');
        }

        if ($this->openAccountStatement() != 200) {
            $this->logout();

            throw new RuntimeException('Unable to open the menu content');
        }

        sleep($this->requestDelay);

        $options = $this->getRequestOptions();
        $options['headers'] = array_merge($options['headers'], [
            'Referer' => $this->effectiveUri
        ]);
        $options['on_stats'] = function (TransferStats $stats) {
            $this->effectiveUri = $stats->getEffectiveUri();
        };

        $options['form_params'] = [
            'value(D1)'       => 0,
            'value(r1)'       => 1,
            'value(startDt)'  => $startDate->day,
            'value(startMt)'  => $startDate->month,
            'value(startYr)'  => $startDate->year,
            'value(endDt)'    => $endDate->day,
            'value(endMt)'    => $endDate->month,
            'value(endYr)'    => $endDate->year,
            'value(fDt)'      => '',
            'value(tDt)'      => '',
            'value(submit1)'  => 'View Account Statement'
        ];

        $url = $this->baseUri.'/accountstmt.do?value(actions)=acctstmtview';

        try {
            $response = $this->client()->request('POST', $url, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException(Psr7\str($e->getResponse()));
            } else {
                throw new RuntimeException($e->getMessage());
            }
        }

        $items = new Collection();

        if ($response->getStatusCode() == 200) {
            $body = (string) $response->getBody();
            $body = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $body);

            $items = $this->extractStatements($startDate, $body);
        }

        return $items;
    }

    /**
     * Extract statements from page.
     *
     * @param  \Carbon\Carbon  $date
     * @param  string          $html
     * @return \Collection
     * @throws \RuntimeException
     */
    private function extractStatements(Carbon $startDate, $html)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');

        // set error level
        $internalErrors = libxml_use_internal_errors(true);
        
        $dom->recover = true;
        $dom->strictErrorChecking = false;
        $dom->loadHTML($html);

        // Restore error level
        libxml_use_internal_errors($internalErrors);

        $tables = $dom->getElementsByTagName('table');
        $items  = new Collection();

        if ( ! $tables instanceOf DOMNodeList) {
            return $items;
            // throw new RuntimeException('Required "table" HTML tag does not exist in page');
        }

        if ( ! isset($tables[4])) {
            throw new RuntimeException('Required "table" HTML tag does not found at index #4');
        }

        $rows = $tables[4]->childNodes;

        if ( ! $rows instanceOf DOMNodeList) {
            throw new RuntimeException('Required "tr" HTML tags does not found below "tbody"');
        }

        if ($rows->length == 1) {
            return $items;
        }

        for($i = 1; $i < $rows->length; ++$i) {
            $columns = $rows->item($i)->childNodes;
            
            if ($columns instanceOf DOMNodeList) {
                if ($columns->item(0)->firstChild instanceOf DOMNode) {
                    $recordDate = $columns->item(0)->firstChild->nodeValue;

                    $recordDate = $columns->item(0)->nodeValue;
                    $recordDate = strtoupper($recordDate);
                    $recordDate = trim($recordDate);
                    if ($recordDate == 'PEND') {
                        $date = $startDate->format('Y-m').'-00';
                    } else {
                        $recordDate = explode('/', $recordDate);
                        $date = $startDate->format('Y-m').'-'.$recordDate[0];
                    }
                } else {
                    $date = null;
                }

                $description = null;
                if ($columns->item(2)->firstChild instanceOf DOMNode) {

                    $description = $this->DOMinnerHTML($columns->item(2)->firstChild);
                    $description = str_replace('<br>', '|', $description);
                    $description = strip_tags($description);
                    $description = trim($description);
                    $description = rtrim($description, '|');
                    $description = preg_replace('/([ ]+)\|/', '|', $description);
                }

                $amount = null;
                if ($columns->item(6)->firstChild instanceOf DOMNode) {
                    $amount = $columns->item(6)->firstChild->nodeValue;
                    $amount = str_replace(',', '', $amount);
                }

                $type = null;
                if ($columns->item(8)->firstChild instanceOf DOMNode) {
                    $type = strtoupper($columns->item(8)->firstChild->nodeValue);
                }

                $balance = null;
                if ($columns->item(10)->firstChild instanceOf DOMNode) {
                    $balance = $columns->item(10)->firstChild->nodeValue;
                    $balance = str_replace(',', '', $balance);
                }

                $uuidName = serialize($this->additionalEntityParams);
                $uuidName .= '.'.trim($description);
                $uuidName .= '.'.trim($type);
                $uuidName .= '.'.trim($amount);
                $uuidName .= '.'.trim($balance);

                $data = array_merge($this->additionalEntityParams, [
                    'unique_id'   => $this->generateIdentifier($uuidName), 
                    'date'        => trim($date), 
                    'description' => trim($description), 
                    'amount'      => trim($amount), 
                    'type'        => trim($type)
                ]);

                $items->push(new Entity($data));
            }
        }

        return $items;
    }

    /**
     * Do open menu.
     *
     * @return int
     * @throws \RuntimeException
     */
    private function openMenu()
    {
        sleep($this->requestDelay);

        $options = $this->getRequestOptions();
        $options['headers'] = array_merge($options['headers'], [
            'Referer' => $this->effectiveUri
        ]);
        $options['on_stats'] = function (TransferStats $stats) {
            $this->effectiveUri = $stats->getEffectiveUri();
        };

        $url = $this->baseUri.'/nav_bar/menu_bar.htm';

        try {
            $response = $this->client()->request('GET', $url, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException(Psr7\str($e->getResponse()));
            } else {
                throw new RuntimeException($e->getMessage());
            }
        }

        return $response->getStatusCode();
    }

    /**
     * Do open account information menu.
     *
     * @return int
     * @throws \RuntimeException
     */
    private function openAccountInformationMenu()
    {
        sleep($this->requestDelay);

        $options = $this->getRequestOptions();
        $options['headers'] = array_merge($options['headers'], [
            'Referer' => $this->effectiveUri
        ]);
        $options['on_stats'] = function (TransferStats $stats) {
            $this->effectiveUri = $stats->getEffectiveUri();
        };

        $url = $this->baseUri.'/nav_bar/account_information_menu.htm';

        try {
            $response = $this->client()->request('GET', $url, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException(Psr7\str($e->getResponse()));
            } else {
                throw new RuntimeException($e->getMessage());
            }
        }

        return $response->getStatusCode();
    }

    /**
     * Do open account statement page.
     *
     * @return int
     * @throws \RuntimeException
     */
    private function openAccountStatement()
    {
        sleep($this->requestDelay);

        $options = $this->getRequestOptions();
        $options['headers'] = array_merge($options['headers'], [
            'Referer' => $this->effectiveUri
        ]);
        $options['on_stats'] = function (TransferStats $stats) {
            $this->effectiveUri = $stats->getEffectiveUri();
        };

        $url = $this->baseUri.'/accountstmt.do?value(actions)=acct_stmt';

        try {
            $response = $this->client()->request('POST', $url, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException(Psr7\str($e->getResponse()));
            } else {
                throw new RuntimeException($e->getMessage());
            }
        }

        return $response->getStatusCode();
    }

    /**
     * {@inheritdoc}
     */
    public function logout()
    {
        if ( ! $this->isLoggedIn) {
            return false;
        }

        sleep($this->requestDelay);

        $options = $this->getRequestOptions();
        $options['headers'] = array_merge($options['headers'], [
            'Referer' => $this->baseUri.'/top.htm'
        ]);

        $url = $this->baseUri.'/authentication.do?value(actions)=logout';

        try {
            $response = $this->client()->request('GET', $this->loginUri, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException(Psr7\str($e->getResponse()));
            } else {
                throw new RuntimeException($e->getMessage());
            }
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode == 200) {
            $this->isLoggedIn = false;
        }

        return $statusCode;
    }
}
