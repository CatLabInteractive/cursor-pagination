# cursor-pagination
[![Build Status](https://travis-ci.org/CatLabInteractive/cursor-pagination.svg?branch=master)](https://travis-ci.org/CatLabInteractive/cursor-pagination)

Cursor pagination for REST APIs.

Goal
====
Provide cursor based pagination to APIs or webservices. The library
calculates the sql query parameters and the cursors that should be 
passed through the requests.

So, like LIMIT {offset}, {records}?
===================================
Cursor based pagination is generally more performant than page based 
pagination (using SQL offset) since it filters the records instead 
of limiting the amount of records returned.

For changing data cursor based pagination is also more correct, since 
adding a row to the beginning of a list might shift all records.

For more information, head over to this [excellent article](https://www.sitepoint.com/paginating-real-time-data-cursor-based-pagination/).

Example
=======
```php
<?php 

use CatLab\Base\Models\Database\OrderParameter;
use CatLab\CursorPagination\CursorPaginationBuilder;

require '../vendor/autoload.php';
require 'helpers.php';
$pdo = require 'mockdata.php';

$builder = new CursorPaginationBuilder();

// Show 5 records on each page
$builder->limit(isset($_GET['records']) ? $_GET['records'] : 5);

// Register properties
$builder->registerPropertyName('id', 'public_id');
$builder->registerPropertyName('name', 'public_name');
$builder->registerPropertyName('score', 'public_score');

/**
 * Set select order
 */

// Order by score desc
$builder->orderBy(new OrderParameter('score', OrderParameter::DESC));

// Same score? Order by name asc
$builder->orderBy(new OrderParameter('name', OrderParameter::ASC));

// Same score and same name? Sort on ID
$builder->orderBy(new OrderParameter('id', OrderParameter::ASC));

// Set the request parameters
$builder->setRequest($_GET);

/**
 * Select and output data
 */
// Build the select query
$query = $builder->build();

// Load the data
$sql = $query->toQuery($pdo, 'entries');
$results = $pdo->query($sql)->fetchAll();

// Post process results. Very important. Don't forget.
$results = $builder->processResults($query, $results);

// Display the records
$table = new Table([ 'id', 'name', 'score' ]);
$table->open();
foreach ($results as $v) {
    $table->row($v);
}
$table->close();

$table->navigation($builder->getNavigation());
```

This example is live at https://dev.catlab.eu/cursor-pagination/examples/