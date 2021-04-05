<?php

namespace Tleckie\Cache\Couchbase\Psr6;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use function is_int;

/**
 * Class Item
 *
 * @package Tleckie\Cache\Couchbase\Psr6
 * @author  Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class Item implements CacheItemInterface
{
    /** @var string */
    protected string $key;

    /** @var string|array|object|null */
    protected $value;

    /** @var int */
    protected int $ttl;

    /** @var bool */
    private bool $isHit = false;

    /**
     * Item constructor.
     *
     * @param string $key
     */
    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function set(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @param DateTimeInterface|null $expiration
     * @return $this
     */
    public function expiresAt(?DateTimeInterface $expiration): self
    {
        $this->ttl = 0;
        if ($expiration instanceof DateTimeInterface) {
            $now = new DateTime('now');
            $this->ttl = $expiration->getTimestamp() - $now->getTimestamp();
        }

        return $this;
    }

    /**
     * @param int|DateInterval|null $time
     * @return $this
     */
    public function expiresAfter(int|DateInterval|null $time): self
    {
        $this->ttl = 0;

        if ($time instanceof DateInterval) {
            $this->ttl = DateTime::createFromFormat('U', 0)->add($time)->format('U');
        } elseif (is_int($time)) {
            $this->ttl = $time;
        }

        return $this;
    }
}