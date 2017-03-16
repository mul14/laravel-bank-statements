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

class Account extends Provider
{
    /**
     * {@inheritdoc}
     */
    public function search(Array $params = [], $page = 1, $limit = 10)
    {
        $params = array_merge([], $params);

        if (empty($page)) {
            $page = 1;
        }

        $fromSql = '(';
        $fromSql .= 'SELECT `id` FROM `'.$this->table.'`';

        $useWhere = false;
        $isUseWhere = false;

        if ($useWhere) {
            $fromSql .= ' WHERE';
        }

        $fromSql .= ' ORDER BY `created_at` DESC';

        if ($limit > 0) {
            $fromSql .= ' limit '.$limit.' offset '.($page - 1) * $limit;
        }

        $fromSql .= ') o';

        $query = $this->getTable()->select($this->table.'.id');

        $this->total = $query->count();

        $query = $this->getTable()->newQuery()->select($this->table.'.*')
                    ->from($this->resolver->raw($fromSql))
                    ->join($this->table, $this->table.'.id', '=', 'o.id')
                    ->orderBy($this->table.'.created_at', 'DESC');

        unset($fromSql);

        return $query->get();
    }
}
