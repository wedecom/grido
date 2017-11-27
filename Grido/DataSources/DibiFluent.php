<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\DataSources;

/**
 * Dibi Fluent data source.
 *
 * @package     Grido
 * @subpackage  DataSources
 * @author      Petr Bugyík
 *
 * @property-read \DibiFluent $fluent
 * @property-read int $limit
 * @property-read int $offset
 * @property-read int $count
 * @property-read array $data
 */
class DibiFluent extends \Nette\Object implements IDataSource
{
    /** @var \DibiFluent */
    protected $fluent;

    /** @var int */
    protected $limit;

    /** @var int */
    protected $offset;

    /**
     * @param \DibiFluent $fluent
     */
    public function __construct(\DibiFluent $fluent)
    {
        $this->fluent = $fluent;
    }

    /**
     * @return \DibiFluent
     */
    public function getFluent()
    {
        return $this->fluent;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param Grido\Components\Filters\Condition $condition
     * @param \DibiFluent $fluent
     */
    protected function makeWhere(\Grido\Components\Filters\Condition $condition, \DibiFluent $fluent = NULL)
    {
        $fluent = $fluent === NULL
            ? $this->fluent
            : $fluent;

        if ($condition->callback) {
            callback($condition->callback)->invokeArgs(array($condition->value, $fluent));
        } else {
            call_user_func_array(array($fluent, 'where'), $condition->__toArray('[', ']'));
        }
    }

    /*********************************** interface IDataSource ************************************/


    private function modifyFluentSelect($columns)
	{
        $fluent = clone $this->fluent;
        $reflection = new \ReflectionObject($fluent);
        $property = $reflection->getProperty('clauses');
        $property->setAccessible(TRUE);
        $clauses = $property->getValue($fluent);
        $clauses['SELECT'] = [implode(', ', $columns)];
        $clauses['ORDER BY'] = NULL;
        $property->setValue($fluent, $clauses);
        return $fluent;
	}


    /**
     * @return int
     */
    public function getCount()
    {
    	$fluent = $this->modifyFluentSelect(['Count(*)']);
		$result = $fluent->fetchAll();
		if (count($result) == 1) {
			$x = reset($result[0]);
			return $x;
		} else {
			return FALSE;
		}
    }

    /**
    * Gets aggregated values.
    *
    * @param array $columns
    * @return array|bool
    */
	public function getAggregates($columns)
	{
		$functions = array();
		foreach ($columns as $column) {
			if ($column->aggregateFunction !== NULL) {
				$functions[] = $column->aggregateFunction . '(' . $column->column . ') AS ' . $column->column;
			}
		}
		$fluent = $this->modifyFluentSelect($functions);
		$result = $fluent->fetchAll();
		if (count($result) == 1) {
			return $result[0];
		} else {
			return FALSE;
		}
	}

    /**
     * @return array
     */
    public function getData()
    {
        return $this->fluent->fetchAll($this->offset, $this->limit);
    }

    /**
     * @param array $conditions
     */
    public function filter(array $conditions)
    {
        foreach ($conditions as $condition) {
            $this->makeWhere($condition);
        }
    }

    /**
     * @param int $offset
     * @param int $limit
     */
    public function limit($offset, $limit)
    {
        $this->offset = $offset;
        $this->limit = $limit;
    }

    /**
     * @param array $sorting
     */
    public function sort(array $sorting)
    {
        foreach ($sorting as $column => $sort) {
            $this->fluent->orderBy($column, $sort . ' NULLS LAST');
        }
    }

    /**
     * @param mixed $column
     * @param array $conditions
     * @param int $limit
     * @return array
     */
    public function suggest($column, array $conditions, $limit)
    {
        $fluent = clone $this->fluent;
        foreach ($conditions as $condition) {
            $this->makeWhere($condition, $fluent);
        }

        $items = array();
        $data = $fluent->fetchAll(0, $limit);
        foreach ($data as $row) {
            if (is_string($column)) {
                $value = (string) $row[$column];
            } elseif (is_callable($column)) {
                $value = (string) $column($row);
            } else {
                throw new \InvalidArgumentException('Column of suggestion must be string or callback, ' . gettype($column) . ' given.');
            }

            $items[$value] = $value;
        }

        return array_values($items);
    }
}
