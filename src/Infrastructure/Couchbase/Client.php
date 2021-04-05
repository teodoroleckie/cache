<?php

namespace Tleckie\Cache\Infrastructure\Couchbase;

use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\Collection;
use Couchbase\DocumentNotFoundException;
use Couchbase\UpsertOptions;
use DateInterval;
use DateTime;

/**
 * Class Connection
 *
 * @package Tleckie\Cache\Infrastructure\Couchbase
 * @author  Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class Client
{
    /** @var int */
    private const MAX_SECONDS = 2592000;

    /** @var Cluster */
    private Cluster $cluster;

    /** @var string */
    private string $bucketName;

    /** @var Bucket */
    private Bucket $bucket;

    /** @var string  */
    private string $collectionName;

    /** @var Collection */
    private Collection $collection;

    /** @var string */
    private string $scope;

    /**
     * Client constructor.
     *
     * @param Cluster $cluster
     * @param string  $bucketName
     * @param string  $collectionName
     * @param string  $scope
     */
    public function __construct(
        Cluster $cluster,
        string $bucketName,
        string $collectionName = '',
        string $scope = 'cache'
    )
    {
        $this->cluster = $cluster;
        $this->bucketName = $bucketName;
        $this->collectionName = $collectionName;
        $this->scope = $scope;

        $this->changeBucket($bucketName)
            ->changeCollection($collectionName);

    }

    /**
     * @param string $collectionName
     * @return $this
     */
    public function changeCollection(string $collectionName = ''): Client
    {
        $this->collection = ('' === $collectionName)
            ? $this->bucket->defaultCollection()
            : $this->bucket->scope($this->scope)->collection($collectionName);

        $this->collectionName = $collectionName;

        return $this;
    }

    /**
     * @param string $bucketName
     * @return $this
     */
    public function changeBucket(string $bucketName): Client
    {
        $this->bucketName = $bucketName;
        $this->bucket = $this->cluster->bucket($this->bucketName);

        return $this;
    }

    /**
     * @return string|null
     */
    public function collectionName(): ?string
    {
        return $this->collectionName;
    }

    /**
     * @return string
     */
    public function bucketName(): string
    {
        return $this->bucketName;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        try {
            $this->collection->get($key);
        } catch (DocumentNotFoundException $exception) {
            return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        try {
            $this->collection->remove($key);
        } catch (DocumentNotFoundException $exception) {
            return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->collection->get($key)->content();
    }

    /**
     * @param string $key
     * @param        $value
     * @param        $ttl
     * @return mixed
     */
    public function set(string $key, $value, $ttl): mixed
    {
        $options = new UpsertOptions();
        $options->expiry($this->normalizeTtl($ttl));

        $this->collection->upsert($key, $value, $options);

        return $value;
    }

    /**
     * @param DateInterval|int|null $ttl
     * @return int
     */
    private function normalizeTtl(DateInterval|int|null $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            $ttl = DateTime::createFromFormat('U', '0')
                ->add($ttl)
                ->format('U');
        }

        return $ttl;
    }

    /**
     * @return bool
     */
    public function flush(): bool
    {
        $this->cluster
            ->buckets()
            ->flush($this->bucketName);

        return true;
    }
}