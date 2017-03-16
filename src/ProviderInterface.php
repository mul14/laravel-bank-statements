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

interface ProviderInterface
{
    /**
     * Get a list of all of the data.
     *
     * @return array
     */
    public function all();

    /**
     * Get a single data.
     *
     * @param  mixed  $id
     * @return array
     */
    public function find($id);

    /**
     * Return data collection.
     *
     * @param  array   $params
     * @param  integer $page
     * @param  integer $limit
     * 
     * @return \Collection
     */
    public function search(Array $params = [], $page = 1, $limit = 10);

    /**
     * Create a new item.
     *
     * @param  Array $data
     * 
     * @return Int
     *
     * @throws \RuntimeException
     */
    public function create(Array $data);

    /**
     * Save a item.
     *
     * @param  Int   $id
     * @param  Array $data
     * 
     * @return Int
     *
     * @throws \RuntimeException
     */
    public function save($id, Array $data);

    /**
     * Delete a item.
     *
     * @param  Int  $id
     * 
     * @return Bool
     *
     * @throws \RuntimeException
     */
    public function delete($id);
}
