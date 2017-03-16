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

abstract class Provider implements ProviderInterface
{
    /**
     * The connection resolver implementation.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * The database connection name.
     *
     * @var string
     */
    protected $database;

    /**
     * The database table.
     *
     * @var string
     */
    protected $table;

    /**
     * The query total.
     *
     * @var integer
     */
    protected $total;

    /**
     * Create a new provider.
     *
     * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
     * @param  string  $database
     * @param  string  $table
     * @return void
     */
    public function __construct(ConnectionResolverInterface $resolver, $database, $table)
    {
        $this->resolver = $resolver;
        $this->database = $database;
        $this->table = $table;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->getTable()->orderBy('id', 'desc')->get()->all();
    }

    /**
     * {@inheritdoc}
     */
    public function find($id)
    {
        return $this->getTable()->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function search(Array $params = [], $page = 1, $limit = 10)
    {
        return new Collection();
    }

    /**
     * {@inheritdoc}
     */
    public function create(Array $data)
    {
        return $this->getTable()->insertGetId($data);
    }

    /**
     * {@inheritdoc}
     */
    public function save($id, Array $data)
    {
        return $this->getTable()->where('id', $id)->update($data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        return $this->getTable()->where('id', $id)->delete() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Get a new query builder instance for the table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getTable()
    {
        return $this->resolver->connection($this->database)->table($this->table);
    }
}
