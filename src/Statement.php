<?php

namespace Sule\BankStatements;

/*
 * This file is part of the Sulaeman Bank Statements package.
 *
 * (c) Sulaeman <me@sulaeman.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Collection;

use Carbon\Carbon;

use RuntimeException;
use Sule\BankStatements\RecordNotFoundException;
use Sule\BankStatements\RequireExtendedProcessException;

class Statement extends Provider
{
    /**
     * The Account instances.
     *
     * @var \Sule\BankStatements\Account
     */
    protected $account;

    /**
     * The collector instances.
     *
     * @var array
     */
    protected $collectors;

    /**
     * The temporarly storage path.
     *
     * @var string
     */
    protected $tempStoragePath;

    /**
     * Create a new provider.
     *
     * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
     * @param  string  $database
     * @param  string  $table
     * @param  Account $account
     * @param  array   $collectors
     * @param  string  $tempStoragePath
     * @return void
     */
    public function __construct(
        ConnectionResolverInterface $resolver, 
        $database, 
        $table, 
        Account $account, 
        Array $collectors, 
        $tempStoragePath
    )
    {
        parent::__construct($resolver, $database, $table);

        $this->account         = $account;
        $this->collectors      = $collectors;
        $this->tempStoragePath = $tempStoragePath;
    }

    /**
     * Return the temporary storage path.
     *
     * @return string
     */
    public function getTempStoragePath()
    {
        return $this->tempStoragePath;
    }

    /**
     * {@inheritdoc}
     */
    public function search(Array $params = [], $page = 1, $limit = 10)
    {
        $params = array_merge([
            'bank_account_id' => 0, 
            'from_date'       => '', 
            'end_date'        => '', 
            'type'            => '', 
            'amount'          => 0, 
            'order_by'        => 'created_at', 
            'order'           => 'DESC'
        ], $params);

        if (empty($page)) {
            $page = 1;
        }

        $fromSql = '(';
        $fromSql .= 'SELECT `id` FROM `'.$this->table.'`';

        $useWhere = false;
        $isUseWhere = false;

        if ( ! empty($params['bank_account_id'])
         || ! empty($params['from_date'])
         || ! empty($params['end_date'])
         || ! empty($params['type'])
         || ! empty($params['amount'])) {
            $useWhere = true;
        }

        if ($useWhere) {
            $fromSql .= ' WHERE';
        }

        if ( ! empty($params['bank_account_id'])) {
            if ($isUseWhere) {
                $fromSql .= ' AND';
            }

            $fromSql .= ' `'.$this->table.'`.`bank_account_id` = '.$params['bank_account_id'];

            $isUseWhere = true;
        }

        if ( ! empty($params['from_date'])) {
            if ($isUseWhere) {
                $fromSql .= ' AND';
            }

            $fromSql .= ' `'.$this->table.'`.`transaction_date` >= "'.$params['from_date'].'"';

            $isUseWhere = true;
        }

        if ( ! empty($params['end_date'])) {
            if ($isUseWhere) {
                $fromSql .= ' AND';
            }

            $fromSql .= ' `'.$this->table.'`.`transaction_date` <= "'.$params['end_date'].'"';

            $isUseWhere = true;
        }

        if ( ! empty($params['type'])) {
            if ($isUseWhere) {
                $fromSql .= ' AND';
            }

            $fromSql .= ' `'.$this->table.'`.`type` = "'.$params['type'].'"';

            $isUseWhere = true;
        }

        if ( ! empty($params['amount'])) {
            if ($isUseWhere) {
                $fromSql .= ' AND';
            }

            $fromSql .= ' `'.$this->table.'`.`amount` = '.$params['amount'];

            $isUseWhere = true;
        }

        $fromSql .= ' ORDER BY `'.$params['order_by'].'` '.$params['order'];

        if ($limit > 0) {
            $fromSql .= ' limit '.$limit.' offset '.($page - 1) * $limit;
        }

        $fromSql .= ') o';

        $query = $this->getTable()->select($this->table.'.id');

        if ( ! empty($params['bank_account_id'])) {
            $query->where($this->table.'.bank_account_id', '=', $params['bank_account_id']);
        }

        if ( ! empty($params['from_date'])) {
            $query->where($this->table.'.transaction_date', '>=', $params['from_date']);
        }

        if ( ! empty($params['end_date'])) {
            $query->where($this->table.'.transaction_date', '<=', $params['end_date']);
        }

        if ( ! empty($params['type'])) {
            $query->where($this->table.'.type', '=', $params['type']);
        }

        if ( ! empty($params['amount'])) {
            $query->where($this->table.'.amount', '=', $params['amount']);
        }

        $this->total = $query->count();

        $query = $this->getTable()->newQuery()->select($this->table.'.*')
                    ->from($this->resolver->raw($fromSql))
                    ->join($this->table, $this->table.'.id', '=', 'o.id')
                    ->orderBy($this->table.'.'.$params['order_by'], $params['order']);

        unset($fromSql);

        return $query->get();
    }

    /**
     * Get a single data by params.
     *
     * @param  array  $params
     * @return array
     */
    public function findBy(Array $params)
    {
        $params = array_merge([
            'unique_id' => '', 
            'amount'    => 0
        ], $params);

        $table = $this->getTable();

        if ( ! empty($params['unique_id'])) {
            $table->where($this->table.'.unique_id', '=', $params['unique_id']);
        }

        if ( ! empty($params['amount'])) {
            $table->where($this->table.'.amount', '=', $params['amount']);
        }

        $item = $table->first();

        if (is_null($item)) {
            throw new RecordNotFoundException('Item not found!');
        }

        return $item;
    }

    /**
     * Collect statements.
     * 
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     * @param  array           $params
     * @return void
     * @throws \RuntimeException
     * @throws \FatalThrowableError
     * @throws \ErrorException
     * @throws \Sule\BankStatements\LoginFailureException
     */
    public function collect(Carbon $startDate, Carbon $endDate, Array $params = [])
    {
        $params = array_merge($params, []);

        $accounts = $this->account->all();

        if (empty($accounts)) {
            throw new RuntimeException('No bank accounts registered');
        }

        foreach ($accounts as $account) {
            if (empty($account->collector)) {
                continue;
            }

            if ( ! isset($this->collectors[$account->collector])) {
                throw new RuntimeException('Collector ['.$account->collector.'] is not available');
            }

            $this->collectors[$account->collector]->setTempStoragePath($this->tempStoragePath);
            $this->collectors[$account->collector]->setBaseUri($account->url);
            $this->collectors[$account->collector]->setCredential($account->user_id, decrypt($account->password));
            $this->collectors[$account->collector]->setAdditionalEntityParams([
                'account_id' => $account->id
            ]);

            try {
                $this->collectors[$account->collector]->landing();
            } catch (RequireExtendedProcessException $e) {
                $this->collectors[$account->collector]->saveState($account->collector);
                $this->saveState($account->id);
                throw new RequireExtendedProcessException($account->collector);
            } catch (RuntimeException $e) {
                continue;
            }

            try {
                $this->collectors[$account->collector]->login();
            } catch (LoginFailureException $e) {
                continue;
            }

            try {
                $items = $this->collectors[$account->collector]->collect($startDate, $endDate);

                if ($items->isNotEmpty()) {
                    $this->saveItems($items);
                }
            } catch (FatalThrowableError $e) {
                $this->collectors[$account->collector]->logout();

                throw new RuntimeException($e->getMessage());
            } catch (ErrorException $e) {
                $this->collectors[$account->collector]->logout();

                throw new RuntimeException($e->getMessage());
            } catch (RuntimeException $e) {
                $this->collectors[$account->collector]->logout();

                throw new RuntimeException($e->getMessage());
            }

            $this->collectors[$account->collector]->logout();
        }
    }

    /**
     * Continue collect statements.
     * 
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     * @param  array           $params
     * @return void
     * @throws \RuntimeException
     * @throws \FatalThrowableError
     * @throws \ErrorException
     * @throws \Sule\BankStatements\LoginFailureException
     */
    public function continueCollect(Carbon $startDate, Carbon $endDate, Array $params = [])
    {
        $accounts = $this->account->all();

        if (empty($accounts)) {
            throw new RuntimeException('No bank accounts registered');
        }

        $stateAccountId = $this->getState();

        foreach ($accounts as $account) {
            if (empty($account->collector)) {
                continue;
            }

            if ($account->id > $stateAccountId) {
                continue;
            }

            if ( ! isset($this->collectors[$account->collector])) {
                throw new RuntimeException('Collector ['.$account->collector.'] is not available');
            }

            $this->collectors[$account->collector]->setTempStoragePath($this->tempStoragePath);
            $this->collectors[$account->collector]->setBaseUri($account->url);
            $this->collectors[$account->collector]->setCredential($account->user_id, decrypt($account->password));
            $this->collectors[$account->collector]->setAdditionalEntityParams([
                'account_id' => $account->id
            ]);

            if ($account->id != $stateAccountId) {
                try {
                    $this->collectors[$account->collector]->landing();
                } catch (RequireExtendedProcessException $e) {
                    $this->collectors[$account->collector]->saveState($account->collector);
                    $this->saveState($account->id);
                    throw new RequireExtendedProcessException($account->collector);
                } catch (RuntimeException $e) {
                    continue;
                }
            } else {
                $this->collectors[$account->collector]->restoreState($account->collector);
            }

            try {
                if ($account->id != $stateAccountId) {
                    $this->collectors[$account->collector]->login();
                } else {
                    if ( ! isset($params[$account->collector])) {
                        continue;
                    }

                    if ( ! isset($params[$account->collector]['password'])) {
                        continue;
                    }

                    if (empty($params[$account->collector]['password'])) {
                        continue;
                    }

                    $this->collectors[$account->collector]->setCredential($account->user_id, $params[$account->collector]['password']);

                    $this->collectors[$account->collector]->login();
                }
            } catch (LoginFailureException $e) {
                continue;
            }

            try {
                $items = $this->collectors[$account->collector]->collect($startDate, $endDate);

                if ($items->isNotEmpty()) {
                    $this->saveItems($items);
                }
            } catch (FatalThrowableError $e) {
                $this->collectors[$account->collector]->logout();

                throw new RuntimeException($e->getMessage());
            } catch (ErrorException $e) {
                $this->collectors[$account->collector]->logout();

                throw new RuntimeException($e->getMessage());
            } catch (RuntimeException $e) {
                $this->collectors[$account->collector]->logout();

                throw new RuntimeException($e->getMessage());
            }

            $this->collectors[$account->collector]->logout();
        }

        $this->removeState();
    }

    /**
     * Save items.
     * 
     * @param  \Illuminate\Support\Collection  $items
     * @return void
     */
    private function saveItems(Collection $items)
    {
        foreach ($items as $item) {
            $doUpdate = false;
            
            try {
                $existingItem = $this->findBy([
                    'unique_id' => $item->uniqueId
                ]);

                if ($existingItem->transaction_date != $item->date) {
                    $doUpdate = true;
                }
            } catch (RecordNotFoundException $e) {
                $this->create([
                    'bank_account_id'  => $item->accountId, 
                    'unique_id'        => $item->uniqueId, 
                    'transaction_date' => $item->date, 
                    'description'      => $item->description, 
                    'type'             => $item->type, 
                    'amount'           => $item->amount, 
                    'created_at'       => new Carbon()
                ]);
            }

            if ($doUpdate) {
                $this->save($existingItem->id, [
                    'transaction_date' => $item->date
                ]);
            }
        }
    }

    /**
     * Save current state.
     * 
     * @param  int  $accountId
     */
    private function saveState($accountId)
    {
        $filePath = $this->tempStoragePath.'/bank-statement.txt';
        file_put_contents($filePath, $accountId);
    }

    /**
     * Get current state.
     * 
     * @return int  $accountId
     */
    private function getState()
    {
        $filePath = $this->tempStoragePath.'/bank-statement.txt';
        return (int) file_get_contents($filePath);
    }

    /**
     * Remove current state.
     * 
     * @return void
     */
    private function removeState()
    {
        @unlink($this->tempStoragePath.'/bank-statement.txt');
    }
}
