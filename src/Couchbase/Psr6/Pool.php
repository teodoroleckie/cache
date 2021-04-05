<?php

namespace Tleckie\Cache\Couchbase\Psr6;

use Tleckie\Cache\Couchbase\Client;
use Tleckie\Cache\Couchbase\Psr6\Exception\InvalidArgumentException;
use Couchbase\BaseException;
use Couchbase\DocumentNotFoundException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Closure;

/**
 * Class Pool
 *
 * @package Tleckie\Cache\Couchbase\Psr6
 * @author  Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class Pool implements CacheItemPoolInterface
{
    /** @var string */
    private const INVALID_KEY_CHARS = ':@{}()/\\';

    /** @var Client */
    private Client $client;

    /** @var string */
    private string $namespace;

    /** @var Item[] */
    private array $deferred = [];

    /**
     * Pool constructor.
     *
     * @param Client $client
     * @param string $namespace
     */
    public function __construct(Client $client, string $namespace = '')
    {
        $this->client = $client;
        $this->namespace = $namespace;
    }

    /**
     * @param array $keys
     * @return iterable
     * @throws InvalidArgumentException
     */
    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * @param string $key
     * @return CacheItemInterface
     * @throws InvalidArgumentException
     */
    public function getItem(string $key): CacheItemInterface
    {
        $client = $this->client;
        $this->validateKey($key);
        $namespace  = $this->namespace;

        return Closure::bind(static function ($item) use ($client, $namespace) {

            try {
                $item->value = $client->get($namespace . $item->getKey());
                $item->isHit = true;

            } catch (BaseException) {
            }

            return $item;

        }, null, Item::class)(new Item($key));
    }

    /**
     * @param string $key
     * @return string
     * @throws InvalidArgumentException
     */
    private function validateKey(string $key): string
    {
        $key = $this->namespace . $key;
        if ('' === $key) {
            throw new InvalidArgumentException(sprintf('Invalid key "%s" provided', $key));
        }

        if (strlen($key) > 65) {
            throw new InvalidArgumentException(sprintf('Invalid key "%s" provided', $key));
        }

        $regex = sprintf('/[%s]/', preg_quote(self::INVALID_KEY_CHARS, '/'));
        if (preg_match($regex, $key)) {
            throw new InvalidArgumentException(sprintf('Invalid key "%s" provided', $key));
        }

        return $key;
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function hasItem(string $key): bool
    {
        $key = $this->validateKey($key);

        return $this->client->has( $key);
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        if('' === $this->namespace){
            return $this->client->flush();
        }

        return false;
    }

    /**
     * @param array $keys
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteItems(array $keys): bool
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->deleteItem($key);
        }

        return !in_array(false, $items, true);
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteItem(string $key): bool
    {
        $key = $this->validateKey( $key);

        return $this->client->delete( $key);
    }

    /**
     * @param CacheItemInterface $item
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * @return bool
     */
    public function commit(): bool
    {
        $saved = [];
        foreach ($this->deferred as $item) {
            try {
                $saved[] = $this->save($item);
            } catch (InvalidArgumentException) {
                $saved[] = false;
            }
        }

        return !in_array(false, $saved, true);
    }

    /**
     * @param CacheItemInterface $item
     * @return bool
     * @throws InvalidArgumentException
     */
    public function save(CacheItemInterface $item): bool
    {
        $this->validateKey($item->getKey());

        $namespace = $this->namespace;
        $client = $this->client;

        return Closure::bind(static function ($item) use ($client, $namespace) {

            try {
                $client->set($namespace . $item->getKey(), $item->get(), $item->ttl ?? null);
                $item->isHit = true;

                return true;

            } catch (BaseException) {
            }

            return false;

        }, null, Item::class)($item);
    }

}