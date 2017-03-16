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

class Mandiri extends Web
{
    /**
     * The account ID index.
     *
     * @var int
     */
    public $accountIdIndex = 1;

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
     * The account ID.
     *
     * @var int
     */
    public $accountId;

    /**
     * Set the account ID index to look.
     *
     * @param  int  $index
     * @return void
     */
    public function setAccountIdIndex($index)
    {
        $this->accountIdIndex = $index;
    }

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
            $this->isLanded = true;
        }

        return $statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function login()
    {
        if ( ! $this->isLanded) {
            throw new RuntimeException('Use ->landing() method first');
        }

        if (is_null($this->userId)) {
            throw new RuntimeException('No login uri defined');
        }

        if (is_null($this->password)) {
            throw new RuntimeException('No login uri defined');
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
            'action'   => 'result',
            'userID'   => $this->userId,
            'password' => $this->password,
            'image.x'  => 0,
            'image.y'  => 0
        ];

        $uri = $this->baseUri.'/retail/Login.do';

        try {
            $response = $this->client()->request('POST', $uri, $options);
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
                    if ($input->getAttribute('name') == 'userID') {
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

        if ($this->openMenu() != 200) {
            $this->logout();

            throw new RuntimeException('Unable to open the menu content');
        }

        if ($this->openAccountStatement() != 200) {
            $this->logout();

            throw new RuntimeException('Unable to open the menu content');
        }

        if (is_null($this->accountId)) {
            $this->logout();

            throw new RuntimeException('Unable to find the required account ID');
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
            'action'        => 'result',
            'fromAccountID' => $this->accountId,
            'searchType'    => 'R',
            'fromDay'       => $startDate->day,
            'fromMonth'     => $startDate->month,
            'fromYear'      => $startDate->year,
            'toDay'         => $endDate->day,
            'toMonth'       => $endDate->month,
            'toYear'        => $endDate->year,
            'sortType'      => 'Date',
            'orderBy'       => 'ASC'
        ];

        $url = $this->baseUri.'/retail/TrxHistoryInq.do';

        try {
            $response = $this->client()->request('POST', $url, $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw new RuntimeException(Psr7\str($e->getResponse()));
            } else {
                throw new RuntimeException($e->getMessage());
            }
        }

        $allItems = new Collection();

        if ($response->getStatusCode() == 200) {
            $body = (string) $response->getBody();
            $body = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $body);

            $allItems = $this->extractStatements($body);
        }

        $items = new Collection();
        if ($allItems->isNotEmpty()) {
            $allItems = $allItems->toArray();
            for ($i = (count($allItems) - 1); $i >= 0; --$i) {
                $items->push($allItems[$i]);
            }
        }

        return $items;
    }

    /**
     * Extract statements from page.
     *
     * @param  string  $html
     * @return int
     * @throws \RuntimeException
     */
    private function extractStatements($html)
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
                if ($columns->length == 1 || ! $columns->item(2) instanceOf DomNode) {
                    continue;
                }

                $recordDate = $columns->item(0)->nodeValue;
                $recordDate = explode('/', $recordDate);
                $date = $recordDate[2].'-'.$recordDate[1].'-'.$recordDate[0];

                $description = $this->DOMinnerHTML($columns->item(2));
                $description = str_replace('<br>', '|', $description);
                $description = strip_tags($description);
                $description = trim($description);
                $description = rtrim($description, '|');
                $description = preg_replace('/([ ]+)\|/', '|', $description);

                $firstAmount  = $columns->item(4)->nodeValue;
                $secondAmount = $columns->item(6)->nodeValue;

                $type   = ($firstAmount == '0,00') ? 'CR' : 'DB';
                $amount = ($firstAmount != '0,00') ? $firstAmount : $secondAmount;
                $amount = str_replace('.', '', $amount);
                $amount = str_replace(',', '.', $amount);

                $uuidName = serialize($this->additionalEntityParams);
                $uuidName .= '.'.trim($date);
                $uuidName .= '.'.trim($description);
                $uuidName .= '.'.trim($type);
                $uuidName .= '.'.trim($amount);

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
            'Referer' => $this->baseUri.'/retail/Redirect.do?action=forward'
        ]);
        $options['on_stats'] = function (TransferStats $stats) {
            $this->effectiveUri = $stats->getEffectiveUri();
        };

        $url = $this->baseUri.'/retail/common/menu.jsp';

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

        $url = $this->baseUri.'/retail/TrxHistoryInq.do?action=form';

        try {
            $response = $this->client()->request('GET', $url, $options);
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

            // get account ID
            preg_match_all('/\<option\svalue\=\"([0-9]+)\">([\w\s\-\_\.\,]+)\<\/option\>/', $body, $matches);

            if (!empty($matches)) {
                if (isset($matches[1])) {
                    if (isset($matches[1][($this->accountIdIndex - 1)])) {
                        $this->accountId = $matches[1][($this->accountIdIndex - 1)];
                    }
                }
            }
        }

        return $statusCode;
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
            'Referer' => $this->baseUri.'/retail/common/banner.jsp'
        ]);

        $url = $this->baseUri.'/retail/Logout.do?action=result';

        try {
            $response = $this->client()->request('GET', $url, $options);
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
