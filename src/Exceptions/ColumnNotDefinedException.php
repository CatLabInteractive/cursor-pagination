<?php

namespace CatLab\CursorPagination\Exceptions;

/**
 * Class ColumnNotDefinedException
 * @package CatLab\CursorPagination\Exceptions
 */
class ColumnNotDefinedException extends CursorPaginationException
{
    /**
     * @param $column
     * @return ColumnNotDefinedException
     */
    public static function toPublic($column)
    {
        return new self('Column ' . $column . ' is not register. Please call registerPropertyName() before calling build()');
    }

    /**
     * @param $column
     * @return ColumnNotDefinedException
     */
    public static function toPrivate($column)
    {
        return new self('Property ' . $column . ' could not be found. ' .
            'Please call registerPropertyName() with all sortable properties before calling build()');
    }
}