<?php

namespace CatLab\CursorPagination;

use CatLab\Base\Enum\Operator;
use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Base\Interfaces\Pagination\PaginationBuilder;
use CatLab\Base\Interfaces\Pagination\Navigation;
use CatLab\Base\Models\Database\LimitParameter;
use CatLab\Base\Models\Database\SelectQueryParameters;
use CatLab\Base\Models\Database\OrderParameter;
use CatLab\Base\Models\Database\WhereParameter;
use CatLab\CursorPagination\Exceptions\ColumnNotDefinedException;
use CatLab\CursorPagination\Exceptions\DecodeCursorException;
use InvalidArgumentException;

/**
 * Class PaginationBuilder
 * @package CatLab\CursorPagination
 */
class CursorPaginationBuilder implements PaginationBuilder
{
    const REQUEST_PARAM_BEFORE = 'before';
    const REQUEST_PARAM_AFTER = 'after';

    /**
     * @var int
     */
    private $records;

    /**
     * @var OrderParameter[]
     */
    private $sort = [];

    /**
     * @var array
     */
    private $sortMap = [];

    /**
     * @var mixed[]
     */
    private $first;

    /**
     * @var mixed[]
     */
    private $last;

    /**
     * @var array
     */
    private $privateToPublic = [];

    /**
     * @var array
     */
    private $publicToPrivate = [];

    /**
     * @var string
     */
    private $before;

    /**
     * @var string
     */
    private $after;

    /**
     * @var bool
     */
    private $invertOrder = false;

    /**
     * @param OrderParameter $orderParameter
     * @return $this
     */
    public function orderBy(OrderParameter $orderParameter)
    {
        $this->sort[] = $orderParameter;
        
        $this->sortMap[(string)$orderParameter->getColumn()] = [
            $orderParameter->getColumn(),
            $orderParameter->getDirection()
        ];

        return $this;
    }

    /**
     * @param string $column
     * @param string $public
     * @return $this
     */
    public function registerPropertyName(string $column, string $public)
    {
        $this->publicToPrivate[$public] = $column;
        $this->privateToPublic[$column] = $public;

        return $this;
    }

    /**
     * @param string $column
     * @return string
     * @throws ColumnNotDefinedException
     */
    private function toPublic(string $column)
    {
        if (!isset($this->privateToPublic[$column])) {
            throw ColumnNotDefinedException::toPublic($column);
        }
        
        return $this->privateToPublic[$column];
    }

    /**
     * @param string $public
     * @return string
     * @throws ColumnNotDefinedException
     */
    private function toPrivate(string $public)
    {
        if (!isset($this->publicToPrivate[$public])) {
            throw ColumnNotDefinedException::toPrivate($public);
        }

        return $this->publicToPrivate[$public];
    }

    /**
     * @return OrderParameter[]
     */
    public function getOrderBy()
    {
        return $this->sort;
    }

    /**
     * @param int $records
     * @return $this
     */
    public function limit(int $records)
    {
        $this->records = $records;
        return $this;
    }

    /**
     * @param SelectQueryParameters $queryBuilder
     * @return SelectQueryParameters
     */
    public function build(SelectQueryParameters $queryBuilder = null)
    {
        if (!isset($queryBuilder)) {
            $queryBuilder = new SelectQueryParameters();
        }

        if ($this->records) {
            $queryBuilder->limit(new LimitParameter($this->records));
        }

        if (isset($this->after)) {
            $where = $this->processCursor($this->after, self::REQUEST_PARAM_AFTER);
            $queryBuilder->where($where);
        }

        if (isset($this->before)) {
            $this->invertOrder = true;
            $where = $this->processCursor($this->before, self::REQUEST_PARAM_BEFORE);
            $queryBuilder->where($where);
        }

        foreach ($this->sort as $sort) {
            $dir = $sort->getDirection();
            if ($this->invertOrder) {
                $dir = OrderParameter::invertDirection($dir);
            }
            $queryBuilder->orderBy(new OrderParameter($sort->getColumn(), $dir));
        }

        $queryBuilder->reverse($this->invertOrder);

        return $queryBuilder;
    }

    /**
     * @return Navigation
     */
    public function getNavigation() : Navigation
    {
        $cursor = new Cursors();

        if ($this->first) {
            $cursor->setBefore($this->translateCursor($this->first));
        }

        if ($this->last) {
            $cursor->setAfter($this->translateCursor($this->last));
        }

        return $cursor;
    }

    /**
     * @param array $properties
     * @return PaginationBuilder
     */
    public function setRequest(array $properties)
    {
        if (isset($properties[self::REQUEST_PARAM_BEFORE])) {
            $this->before = $properties[self::REQUEST_PARAM_BEFORE];
        } else {
            $this->before = null;
        }

        if (isset($properties[self::REQUEST_PARAM_AFTER])) {
            $this->after = $properties[self::REQUEST_PARAM_AFTER];
        } else {
            $this->after = null;
        }
    }

    /**
     * @param SelectQueryParameters $query
     * @param mixed[]
     * @return mixed[]
     * @throws InvalidArgumentException
     */
    public function processResults(SelectQueryParameters $query, $results)
    {
        if (!ArrayHelper::isIterable($results)) {
            throw new InvalidArgumentException("Results should be iterable.");
        }

        if ($query->isReverse()) {
            $results = ArrayHelper::reverse($results);
        }

        // Set the first and the last values
        if (count($results) > 0) {
            $this->setFirst($results[0]);
            $this->setLast($results[count($results) - 1]);
        }

        return $results;
    }

    /**
     * @param string $cursor
     * @param string $direction
     * @return WhereParameter
     * @throws DecodeCursorException
     */
    private function processCursor(string $cursor, string $direction)
    {
        // After and before are eachother's opposites
        if ($direction == self::REQUEST_PARAM_AFTER) {
            $opp_c = Operator::GT;
            $opp_ce = Operator::GTE;
            $opp_nc = Operator::LT;
            $opp_nce = Operator::LTE;
        } else {
            $opp_c = Operator::LT;
            $opp_ce = Operator::LTE;
            $opp_nc = Operator::GT;
            $opp_nce = Operator::GTE;
        }

        $decoded = $this->decodeCursor($cursor);

        // We need to work backwards
        $decoded = array_reverse($decoded);

        // The most inner (and least significant column) is the only one that MUST be unique.
        reset($decoded);
        $k = key($decoded);
        $v = current($decoded);

        // Drop it so we don't handle it again
        array_shift($decoded);

        list ($private, $direction) = $this->toPrivateWithDirection($k);
        $where = new WhereParameter(
            $private,
            $direction == OrderParameter::ASC ? $opp_c : $opp_nc,
            $v
        );

        // If we have any parameters left, start piling them up.
        foreach ($decoded as $k => $v) {
            list ($private, $direction) = $this->toPrivateWithDirection($k);

            $outerWhere = new WhereParameter(
                $private,
                $direction === OrderParameter::ASC ? $opp_ce : $opp_nce,
                $v
            );

            $outerWhere->and(
                (new WhereParameter(
                    $private,
                    $direction === OrderParameter::ASC ? $opp_c : $opp_nc,
                    $v
                ))->or($where)
            );

            $where = $outerWhere;
        }

        return $where;
    }

    /**
     * @param $k
     * @return array
     */
    private function toPrivateWithDirection($k)
    {
        $firstChar = mb_substr($k, 0, 1);
        if ($firstChar === '!') {
            $private = $this->toPrivate(mb_substr($k, 1));
            $direction = OrderParameter::DESC;
        } else {
            $private = $this->toPrivate($k);
            $direction = OrderParameter::ASC;
        }

        return [ $private, $direction ];
    }

    /**
     * Translate private cursor in their public counterparts
     * @param $properties
     * @return array
     */
    private function translateCursor($properties)
    {
        $out = [];
        foreach ($this->sortMap as $k => $v) {
            if (isset($properties[$k])) {
                $d = $v[1] === OrderParameter::DESC ? '!' : '';
                $out[$d . $this->toPublic($k)] = $properties[$k];
            }
        }
        return base64_encode(json_encode($out));
    }

    /**
     * @param $cursor
     * @return array
     * @throws DecodeCursorException
     */
    private function decodeCursor($cursor)
    {
        $data = base64_decode($cursor);
        if ($data) {
            $data = json_decode($data, true);
            if ($data) {
                return $data;
            }
        }
        throw new DecodeCursorException("Could not decode cursor.");
    }

    /**
     * @param array $properties
     * @return PaginationBuilder
     */
    public function setFirst($properties) : PaginationBuilder
    {
        if (!ArrayHelper::hasArrayAccess($properties)) {
            throw new InvalidArgumentException(
                "Could not read properties: properties must have ArrayAccess."
            );
        }

        $this->first = $properties;
        return $this;
    }

    /**
     * @param array $properties
     * @return PaginationBuilder
     */
    public function setLast($properties) : PaginationBuilder
    {
        if (!ArrayHelper::hasArrayAccess($properties)) {
            throw new InvalidArgumentException(
                "Could not read properties: properties must have ArrayAccess."
            );
        }

        $this->last = $properties;
        return $this;
    }
}