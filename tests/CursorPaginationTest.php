<?php

namespace Tests;

use CatLab\Base\Models\Database\OrderParameter;
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
            [  1, 'A is for apple',     rand(0, 10) ],
            [  2, 'B is for balloons',  rand(0, 10) ],
            [  3, 'C is for CatLab',    rand(0, 10) ],
            [  4, 'D is for drums',     rand(0, 10) ],
            [  5, 'E is for energy',    rand(0, 10) ],
            [  6, 'F is for fast',      rand(0, 10) ],
            [  7, 'G is great',         rand(0, 10) ],
            [  8, 'H is for Hilde',     rand(0, 10) ],
            [  9, 'I is for ink',       rand(0, 10) ],
            [ 10, 'J is for Jenkins',   rand(0, 10) ],
            [ 11, 'K is for knitting',  rand(0, 10) ],
            [ 12, 'L is for Love',      rand(0, 10) ],
            [ 13, 'M is for Mario',     rand(0, 10) ],
            [ 14, 'N is for Negative',  rand(0, 10) ],
            [ 15, 'O is for Okay',      rand(0, 10) ],
            [ 16, 'P is for Plasma',    rand(0, 10) ],
            [ 17, 'Q is for Quick, best burgers in town', rand(0, 10) ],
            [ 18, 'R is for REST',      rand(0, 10) ],
            [ 19, 'S is for Snake',     rand(0, 10) ],
            [ 20, 'T is for Thijs',     rand(0, 10) ],
            [ 21, 'U is for Universe',  rand(0, 10) ],
            [ 22, 'V is for Venus',     rand(0, 10) ],
            [ 23, 'W is for Wine',      rand(0, 10) ],
            [ 24, 'X is for Xen',       rand(0, 10) ],
            [ 25, 'Y was for Yahoo',    rand(0, 10) ],
            [ 26, 'Z is for Zelda',     rand(0, 10) ]
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
            echo $sql;
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

        return $paginationBuilder->processResults($query, $complete);
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
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ 4, 5, 6], $results);

        // Another next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);

        $results = $this->getIds($builder);

        $this->assertEquals([ 7, 8, 9], $results);

        // Previous page now
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'before' => $next['before']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ 4, 5, 6], $results);
    }

    public function testIdQuery()
    {
        $builder = $this->getCursorPagination();

        // Sort on id (so regular)
        $builder->orderBy(new OrderParameter('id', OrderParameter::ASC));
        $builder->limit(3);

        $sql = $builder->build()->toQuery($this->pdo, 'entries');
        $this->assertEquals('SELECT * FROM entries ORDER BY id ASC LIMIT 3', $sql);
        $results = $this->getIds($builder);

        // Check next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $results = $this->getIds($builder);

        $sql = $builder->build()->toQuery($this->pdo, 'entries');
        $this->assertEquals('SELECT * FROM entries WHERE id > \'3\' ORDER BY id ASC LIMIT 3', $sql);

        // Previous page now
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'before' => $next['before']]);
        $sql = $builder->build()->toQuery($this->pdo, 'entries');
        $results = $this->getIds($builder);

        $this->assertEquals('SELECT * FROM entries WHERE id < \'4\' ORDER BY id DESC LIMIT 3', $sql);
    }

    /**
     *
     */
    public function testNamePagination()
    {
        $builder = $this->getCursorPagination();

        // Sort on id (so regular)
        $builder->orderBy(new OrderParameter('name', OrderParameter::ASC));
        $builder->orderBy(new OrderParameter('id', OrderParameter::ASC));
        $builder->limit(3);

        $results = $this->getIds($builder);
        $this->assertEquals([ 1, 2, 3], $results);

        // Check next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ 4, 5, 6], $results);

        // Another next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);

        $sql = $builder->build()->toQuery($this->pdo, 'entries');
        $this->assertEquals('SELECT * FROM entries WHERE name >= \'F is for fast\' AND (name > \'F is for fast\' OR (id > \'6\')) ORDER BY name ASC, id ASC LIMIT 3', $sql);
        $results = $this->getIds($builder);

        $this->assertEquals([ 7, 8, 9], $results);

        // Previous page now
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'before' => $next['before']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ 4, 5, 6], $results);
    }

    /**
     *
     */
    public function testReversePagination()
    {
        $builder = $this->getCursorPagination();

        // Sort on id (so regular)
        $builder->orderBy(new OrderParameter('name', OrderParameter::DESC));
        $builder->orderBy(new OrderParameter('id', OrderParameter::ASC));
        $builder->limit(3);

        $results = $this->getIds($builder);
        $this->assertEquals([ 26, 25, 24], $results);

        // Check next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $this->assertEquals(
            '{"!public_name":"X is for Xen","public_id":"24"}',
            base64_decode($next['after'])
        );

        $results = $this->getIds($builder);

        $this->assertEquals([ 23, 22, 21], $results);

        // Another next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);

        $sql = $builder->build()->toQuery($this->pdo, 'entries');
        $this->assertEquals(
            'SELECT * FROM entries ' .
            'WHERE name <= \'U is for Universe\' '.
            'AND (name < \'U is for Universe\' OR (id > \'21\')) ' .
            'ORDER BY name DESC, id ASC LIMIT 3',
            $sql
        );
        $results = $this->getIds($builder);

        $this->assertEquals([ 20, 19, 18], $results);

        // Previous page now
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'before' => $next['before']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ 23, 22, 21], $results);
    }

    /**
     *
     */
    public function testScorePagination()
    {
        $builder = $this->getCursorPagination();

        // Sort on id (so regular)
        $builder->orderBy(new OrderParameter('score', OrderParameter::ASC));
        $builder->orderBy(new OrderParameter('name', OrderParameter::ASC));
        $builder->orderBy(new OrderParameter('id', OrderParameter::ASC));
        $builder->limit(100);

        $all = $this->getIds($builder);

        $builder->limit(3);

        $results = $this->getIds($builder);
        $this->assertEquals([ $all[0], $all[1], $all[2] ], $results);

        // Check next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ $all[3], $all[4], $all[5] ], $results);

        // Another next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);

        $results = $this->getIds($builder);

        $this->assertEquals([ $all[6], $all[7], $all[8] ], $results);

        // Previous page now
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'before' => $next['before']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ $all[3], $all[4], $all[5] ], $results);
    }

    /**
     *
     */
    public function testScorePaginationReverse()
    {
        $builder = $this->getCursorPagination();

        // Sort on id (so regular)
        $builder->orderBy(new OrderParameter('score', OrderParameter::DESC));
        $builder->orderBy(new OrderParameter('name', OrderParameter::ASC));
        $builder->orderBy(new OrderParameter('id', OrderParameter::ASC));
        $builder->limit(100);

        $all = $this->getIds($builder);

        $builder->limit(3);

        $results = $this->getIds($builder);
        $this->assertEquals([ $all[0], $all[1], $all[2] ], $results);

        // Check next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ $all[3], $all[4], $all[5] ], $results);

        // Another next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);

        $results = $this->getIds($builder);

        $this->assertEquals([ $all[6], $all[7], $all[8] ], $results);

        // Previous page now
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'before' => $next['before']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ $all[3], $all[4], $all[5] ], $results);
    }

    /**
     *
     */
    public function testScorePaginationNameReverse()
    {
        $builder = $this->getCursorPagination();

        // Sort on id (so regular)
        $builder->orderBy(new OrderParameter('score', OrderParameter::ASC));
        $builder->orderBy(new OrderParameter('name', OrderParameter::DESC));
        $builder->orderBy(new OrderParameter('id', OrderParameter::ASC));
        $builder->limit(100);

        $all = $this->getIds($builder);

        $builder->limit(3);

        $results = $this->getIds($builder);
        $this->assertEquals([ $all[0], $all[1], $all[2] ], $results);

        // Check next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ $all[3], $all[4], $all[5] ], $results);

        // Another next page
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'after' => $next['after']]);

        $results = $this->getIds($builder);

        $this->assertEquals([ $all[6], $all[7], $all[8] ], $results);

        // Previous page now
        $cursor = $builder->getNavigation();
        $next = $cursor->toArray();

        $builder->setRequest([ 'before' => $next['before']]);
        $results = $this->getIds($builder);

        $this->assertEquals([ $all[3], $all[4], $all[5] ], $results);
    }

    /**
     * @expectedException \CatLab\CursorPagination\Exceptions\ColumnNotDefinedException
     */
    public function testUnregisteredPropertyException()
    {
        $paginationBuilder = new CursorPaginationBuilder();
        $paginationBuilder->orderBy(new OrderParameter('foobar', OrderParameter::ASC));
        $query = $paginationBuilder->build();
        $paginationBuilder->processResults($query, [[
            'id' => 1,
            'foobar' => 2
        ]]);
        $paginationBuilder->getNavigation();
    }
}