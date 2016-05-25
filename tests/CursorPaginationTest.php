<?php

namespace Tests;

use CatLab\Base\Models\Database\LimitParameter;
use CatLab\Base\Models\Database\OrderParameter;
use CatLab\Base\Models\Database\SelectQueryParameters;
use CatLab\CursorPagination\CursorPaginationBuilder;
use PDO;
use PHPUnit_Framework_TestCase;

/**
 * Class CursorPaginationTest
 */
class CursorPaginationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PDO
     */
    private $pdo;

    /**
     *
     */
    protected function setUp()
    {
        $data = [
            [  1, 'A is for apple',     5 ],
            [  2, 'B is for balloons',  5 ],
            [  3, 'C is for CatLab',    5 ],
            [  4, 'D is for drums',     5 ],
            [  5, 'E is for energy',    4 ],
            [  6, 'F is for fast',      4 ],
            [  7, 'G is great',         4 ],
            [  8, 'H is for Hilde',     4 ],
            [  9, 'I is for ink',       2 ],
            [ 10, 'J is for Jenkins',   2 ],
            [ 11, 'K is for knitting',  2 ],
            [ 12, 'L is for Love',      2 ],
            [ 13, 'M is for Mario',     3 ],
            [ 14, 'N is for Negative',  3 ],
            [ 15, 'O is for Okay',      3 ],
            [ 16, 'P is for Plasma',    9 ],
            [ 17, 'Q is for Quick, best burgers in town', 9 ],
            [ 18, 'R is for REST',      9 ],
            [ 19, 'S is for Snake',     9 ],
            [ 20, 'T is for Thijs',     7 ],
            [ 21, 'U is for Universe',  7 ],
            [ 22, 'V is for Venus',     7 ],
            [ 23, 'W is for Wine',      7 ],
            [ 24, 'X is for Xen',       7 ],
            [ 25, 'Y was for Yahoo',    7 ],
            [ 26, 'Z is for Zelda',     2 ]
        ];

        $this->pdo = new PDO('sqlite::memory:');

        $this->pdo->exec('
            CREATE TABLE `entries` (
              `id` int(11) NOT NULL,
              `name` varchar(50) NOT NULL,
              `score` int(11) NOT NULL,
              `created` datetime NOT NULL
            )
        ');

        $insert = $this->pdo->prepare("
          INSERT INTO 
            entries (id, name, score, created) 
          VALUES 
            (?, ?, ?, ?)
        ");

        foreach ($data as $v) {
            $v[] = (new \DateTime())->format('Y-m-d H:i:s');
            $insert->execute($v);
        }
    }

    /**
     * @return CursorPaginationBuilder
     */
    private function getCursorPagination()
    {
        $paginationBuilder = new CursorPaginationBuilder();
        $paginationBuilder->registerPropertyName('id', 'public_id');
        $paginationBuilder->registerPropertyName('name', 'public_name');
        $paginationBuilder->registerPropertyName('score', 'public_score');

        return $paginationBuilder;
    }

    private function getModels(CursorPaginationBuilder $paginationBuilder)
    {
        $query = $paginationBuilder->build();

        $sql = $query->toQuery($this->pdo, 'entries');
        $results = $this->pdo->query($sql);
        if (!$results) {
            print_r($this->pdo->errorInfo());
            return [];
        }

        $complete = [];
        foreach ($results as $v) {
            $complete[] = [
                'id' => $v['id'],
                'name' => $v['name'],
                'score' => $v['score']
            ];
        }

        if (count($complete) == 0) {
            return [];
        }

        if ($query->isReverse()) {
            $complete = array_reverse($complete);
        }

        $paginationBuilder->setFirst($complete[0]);
        $paginationBuilder->setLast($complete[count($complete) - 1]);

        return $complete;
    }

    /**
     * @param CursorPaginationBuilder $paginationBuilder
     * @return int[]
     */
    private function getIds(CursorPaginationBuilder $paginationBuilder)
    {
        $out = [];
        foreach ($this->getModels($paginationBuilder) as $model) {
            $out[] = $model['id'];
        }
        return $out;
    }

    /**
     *
     */
    public function testIdPagination()
    {
        $builder = $this->getCursorPagination();

        // Sort on id (so regular)
        $builder->orderBy(new OrderParameter('id', OrderParameter::ASC));
        $builder->limit(3);
        
        $results = $this->getIds($builder);
        $this->assertEquals([ 1, 2, 3], $results);

        // Check next page
        $cursor = $builder->getCursors();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ 4, 5, 6], $results);

        // Another next page
        $cursor = $builder->getCursors();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ 7, 8, 9], $results);

        // Previous page now
        $cursor = $builder->getCursors();
        $next = $cursor->toArray();

        $builder->setRequest([ 'before' => $next['before']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ 4, 5, 6], $results);
    }
}