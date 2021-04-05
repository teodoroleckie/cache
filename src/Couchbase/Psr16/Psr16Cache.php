<?php

namespace Tleckie\Cache\Couchbase\Psr16;

use Tleckie\Cache\Couchbase\Client;
use Tleckie\Cache\Couchbase\Psr16\Exception\InvalidArgumentException;
use Couchbase\BaseException;
use Couchbase\DocumentNotFoundException;
use Psr\SimpleCache\CacheInterface;

/**
 * Class Psr16Cache
 *
 * @author Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class Psr16Cache implements CacheInterface
{
    /** @var string */
    private const INVALID_KEY_CHARS = ':@{}()/\\';

    /** @var Client */
    private Client $client;

    /** @var string */
    private string $namespace;

    /**
     * Psr16Cache constructor.
     *
     * @param Client $connection
     * @param string $namespace
     */
    public function __construct(
        Client $connection,
        string $namespace = ''
    )
    {
        $this->client = $connection;
        $this->namespace = $namespace;
    }

    /**
     * @param iterable $keys
     * @param null     $default
     * @return array
     * @throws InvalidArgumentException
     */
    public function getMultiple($keys, $default = null): array
    {
        $items = [];

        if (!is_array($keys)) {
            throw new InvalidArgumentException('The key must be an array');
        }

        foreach ($keys as $key) {
            $items[$key] = $this->get($key, $default);
        }

        return $items;
    }

    /**
     * @param string $key
     * @param null   $default
     * @return array|mixed|object|string|null
     * @throws InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        $key = $this->key($key);
        $this->validateKey($key);

        try {
            return $this->client->get($key);
        } catch (DocumentNotFoundException) {

        }

        return $default;
    }

    /**
     * @param string $key
     * @return string
     */
    private function key(string $key): string
    {
        return $this->namespace . $key;
    }

    /**
     * @param string $key
     * @throws InvalidArgumentException
     */
    private function validateKey(string $key): void
    {
        if ('' === $key) {
            throw new InvalidArgumentException(sprintf('Invalid key "%s" provided', $key));
        }

        if ('0' === $key) {
            return;
        }

        if (strlen($key) > 65) {
            throw new InvalidArgumentException(sprintf('Invalid key "%s" provided', $key));
        }

        $regex = sprintf('/[%s]/', preg_quote(self::INVALID_KEY_CHARS, '/'));
        if (preg_match($regex, $key)) {
            throw new InvalidArgumentException(sprintf('Invalid key "%s" provided', $key));
        }
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        if ('' === $this->namespace) {
            return $this->client->flush();
        }

        return false;
    }

    /**
     * @param iterable $values
     * @param null     $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function setMultiple($values, $ttl = null): bool
    {
        if (!is_array($values)) {
            throw new InvalidArgumentException('The values must be an array [key => value, ...]');
        }

        foreach ($values as $key => $value) {
            if (false === $this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param null   $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function set($key, $value, $ttl = null): bool
    {
        $key = $this->key($key);
        $this->validateKey($key);

        try {
            $this->client->set($key, $value, $ttl);
        } catch (BaseException) {
            return false;
        }

        return true;
    }

    /**
     * @param iterable $keys
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteMultiple($keys): bool
    {
        if (!is_array($keys)) {
            throw new InvalidArgumentException('The key must be an array');
        }

        foreach ($keys as $key) {
            if (false === $this->delete($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function delete($key): bool
    {
        $key = $this->key($key);

        $this->validateKey($key);

        return $this->client->delete($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key): bool
    {
        return $this->client->has($key);
    }


}