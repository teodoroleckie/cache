<?php
namespace Tleckie\Cache\Infrastructure\Couchbase\Psr6\Exception;

use \Psr\Cache\CacheException as ParentCacheException;
use Exception;

/**
 * Class CacheException
 *
 * @package Tleckie\Cache\Infrastructure\Couchbase\Psr16\Exception
 * @author  Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class CacheException extends Exception implements ParentCacheException {

}