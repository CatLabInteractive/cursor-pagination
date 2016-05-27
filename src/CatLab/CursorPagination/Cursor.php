<?php

namespace CatLab\CursorPagination;

use CatLab\Base\Interfaces\Pagination\PaginationCursor;

/**
 * Class Cursor
 * @package CatLab\CursorPagination
 */
class Cursor implements PaginationCursor
{
    /**
     * @var string
     */
    private $before;

    /**
     * @var string
     */
    private $after;

    /**
     * @param string $first
     * @return $this
     */
    public function setBefore($first)
    {
        $this->before = $first;
        return $this;
    }

    /**
     * @param string $last
     * @return $this
     */
    public function setAfter($last)
    {
        $this->after = $last;
        return $this;
    }

    /**
     * @return mixed
     */
    public function toArray()
    {
        return [
            CursorPaginationBuilder::REQUEST_PARAM_BEFORE => $this->before,
            CursorPaginationBuilder::REQUEST_PARAM_AFTER => $this->after
        ];
    }

    /**
     * @return mixed[]
     */
    public function getNext()
    {
        if (isset($this->after)) {
            return [ CursorPaginationBuilder::REQUEST_PARAM_AFTER => $this->after ];
        }
        return null;
    }

    /**
     * @return mixed[]
     */
    public function getPrevious()
    {
        if (isset($this->before)) {
            return [ CursorPaginationBuilder::REQUEST_PARAM_BEFORE => $this->before ];
        }
        return null;
    }
}