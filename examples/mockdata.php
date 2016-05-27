<?php

$data = [
    [  1, 'A is for apple',     10 ],
    [  2, 'B is for balloons',   9 ],
    [  3, 'C is for CatLab',     8 ],
    [  4, 'D is for drums',     10 ],
    [  5, 'E is for energy',    10 ],
    [  6, 'F is for fast',       4 ],
    [  7, 'G is great',         10 ],
    [  8, 'H is for Hilde',      4 ],
    [  9, 'I is for ink',        2 ],
    [ 10, 'J is for Jenkins',    5 ],
    [ 11, 'K is for knitting',   4 ],
    [ 12, 'L is for Love',       3 ],
    [ 13, 'M is for Mario',      9 ],
    [ 14, 'N is for Negative',   8 ],
    [ 15, 'O is for Okay',       3 ],
    [ 16, 'P is for Plasma',     9 ],
    [ 17, 'Q is for Quick',      8 ],
    [ 18, 'R is for REST',       8 ],
    [ 19, 'S is for Snake',      5 ],
    [ 20, 'T is for Thijs',      3 ],
    [ 21, 'U is for Universe',   6 ],
    [ 22, 'V is for Venus',      5 ],
    [ 23, 'W is for Wine',       5 ],
    [ 24, 'X is for Xen',        3 ],
    [ 25, 'Y was for Yahoo',     7 ],
    [ 26, 'Z is for Zelda',      7 ]
];

$pdo = new PDO('sqlite::memory:');

$pdo->exec('
    CREATE TABLE `entries` (
      `id` int(11) NOT NULL,
      `name` varchar(50) NOT NULL,
      `score` int(11) NOT NULL,
      `created` datetime NOT NULL
    )
');

$insert = $pdo->prepare("
    INSERT INTO 
      entries (id, name, score, created) 
    VALUES 
      (?, ?, ?, ?)
");

foreach ($data as $v) {
    $v[] = (new \DateTime())->format('Y-m-d H:i:s');
    $insert->execute($v);
}

return $pdo;