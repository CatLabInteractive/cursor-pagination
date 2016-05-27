<?php

class Table
{
    /**
     * @var string[]
     */
    private $columns;

    /**
     * Table constructor.
     * @param array $columns
     */
    public function __construct(array $columns)
    {
        $this->columns = $columns;
    }

    public function open()
    {
        echo '<table>';

        echo '<tr>';
        foreach ($this->columns as $v) {
            echo '<th>' . $v . '</th>';
        }
        echo '</tr>';
    }

    public function row($data)
    {
        echo '<tr>';
        foreach ($this->columns as $v) {
            echo '<td>' . $data[$v] . '</td>';
        }
        echo '</tr>';
    }

    /**
     * @param \CatLab\Base\Interfaces\Pagination\PaginationCursor $cursor
     */
    public function navigation(\CatLab\Base\Interfaces\Pagination\PaginationCursor $cursor)
    {
        echo '<ul>';
        if ($previous = $cursor->getPrevious()) {
            echo '<li><a href="index.php?' . http_build_query($previous) . '">Previous</a></li>';
        }

        if ($next = $cursor->getNext()) {
            echo '<li><a href="index.php?' . http_build_query($next) . '">Next</a></li>';
        }
        echo '</ul>';
    }

    public function close()
    {
        echo '</table>';
    }
}