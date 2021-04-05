```php
<?php

include_once "vendor/autoload.php";

use Couchbase\Cluster;
use Couchbase\ClusterOptions;
use Tleckie\Cache\Couchbase\Client;
use Tleckie\Cache\Couchbase\Psr16\Psr16Cache;
use Tleckie\Cache\Couchbase\Psr6\Item;
use Tleckie\Cache\Couchbase\Psr6\Pool;


$options = new ClusterOptions();
$options->credentials("user", "pass");
$client = new Client(new Cluster("couchbase://localhost/", $options), 'bucketName');


//psr6
$pool = new Pool($client);

$item = new Item('item.key');
$item->set(['name'=> 'My Name', 'age' => 32]);
$item->expiresAt(3600);
$pool->save($item);

$item = $pool->getItem('item.key');
var_dump($item);
var_dump($item->get());
var_dump($item->isHit());


//psr16
$cache = new Psr16Cache($client);

$cache->set(
    'key', 
    ['name'=> 'My Name', 'age' => 32], 
    3600
);

var_dump($cache->get('key'));



```