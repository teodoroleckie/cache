<?php

namespace Tleckie\Cache\Tests\Couchbase;

use Tleckie\Cache\Couchbase\Client;
use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\Collection;
use Couchbase\DocumentNotFoundException;
use Couchbase\GetResult;
use Couchbase\MutationResultImpl;
use Couchbase\Scope;
use Couchbase\StoreResultImpl;
use Couchbase\UpsertOptions;
use Couchbase\BucketManager;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class CbTest
 *
 * @covers  \Tleckie\Cache\Couchbase\Client
 * @package Tleckie\Cache\Tests\Couchbase
 * @author  Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class ClientTest extends TestCase
{
    /** @var Client */
    private Client $client;

    /** @var Cluster|MockObject */
    private Cluster|MockObject $clusterMock;

    /** @var Bucket|MockObject */
    private Bucket|MockObject $bucketMock;

    /** @var  Collection|MockObject */
    private Collection|MockObject $collectionMock;

    public function setUp(): void
    {
        parent::setUp();
        $this->clusterMock = $this->createMock(Cluster::class);
    }

    public function testBucketName(): void
    {
        $this->createInstance();

        static::assertEquals(
            'bucketName',
            $this->client->bucketName()
        );
    }

    private function createInstance(): void
    {
        $this->client = new Client(
            $this->clusterMock,
            'bucketName',
            'collectionName',
            'cache'
        );
    }

    public function testCollectionName(): void
    {
        $this->createInstance();

        static::assertEquals(
            'collectionName',
            $this->client->collectionName()
        );
    }

    public function testConfigureBucketAndDefaultCollection(): void
    {
        $this->configureMock();
        $this->createInstance();
    }

    private function configureMock(): void
    {
        $this->collectionMock = $this->createMock(Collection::class);
        $scopeMock = $this->createMock(Scope::class);
        $this->bucketMock = $this->createMock(Bucket::class);

        $this->bucketMock->expects(static::once())
            ->method('scope')
            ->with('cache')
            ->willReturn($scopeMock);

        $scopeMock->expects(static::once())
            ->method('collection')
            ->with('collectionName')
            ->willReturn($this->collectionMock);

        $this->clusterMock->expects(static::once())
            ->method('bucket')
            ->with('bucketName')
            ->willReturn($this->bucketMock);
    }

    public function testChangeBucket(): void
    {
        $this->createInstance();

        $this->bucketMock = $this->createMock(Bucket::class);

        $this->clusterMock->expects(static::once())
            ->method('bucket')
            ->with('newBucketName')
            ->willReturn($this->bucketMock);

        $this->client->changeBucket('newBucketName');

        static::assertEquals(
            'newBucketName',
            $this->client->bucketName()
        );
    }

    public function testChangeToDefaultCollection(): void
    {
        $this->configureMock();

        $this->bucketMock->expects(static::once())
            ->method('defaultCollection')
            ->willReturn($this->collectionMock);

        $this->createInstance();
        $this->client->changeCollection();

        static::assertEquals(
            '',
            $this->client->collectionName()
        );
    }

    public function testHasNotItemThenReturnFalse(): void
    {
        $this->configureMock();
        $this->createInstance();

        $this->collectionMock->expects(static::once())
            ->method('get')
            ->with('key')
            ->willThrowException(new DocumentNotFoundException());

        static::assertFalse($this->client->has('key'));
    }

    public function testHasItemThenReturnTrue(): void
    {
        $this->configureMock();
        $this->createInstance();

        $resultMock = $this->createMock(GetResult::class);

        $this->collectionMock->expects(static::once())
            ->method('get')
            ->with('key')
            ->willReturn($resultMock);

        static::assertTrue($this->client->has('key'));
    }

    public function testGetHasNotItemThenThrowException(): void
    {
        $this->configureMock();
        $this->expectException(DocumentNotFoundException::class);

        $this->createInstance();

        $this->collectionMock->expects(static::once())
            ->method('get')
            ->with('key')
            ->willThrowException(new DocumentNotFoundException());

        $this->client->get('key');
    }

    public function testGetHasItemThenReturnValue(): void
    {
        $this->configureMock();
        $this->createInstance();

        $resultMock = $this->createMock(GetResult::class);

        $returnValue = ['name' => 'My Name', 'age' => 25];

        $resultMock->expects(static::once())
            ->method('content')
            ->willReturn($returnValue);

        $this->collectionMock->expects(static::once())
            ->method('get')
            ->with('key')
            ->willReturn($resultMock);

        static::assertEquals(
            $returnValue,
            $this->client->get('key')
        );
    }

    public function testDeleteHasNotItemThenReturnFalse(): void
    {
        $this->configureMock();
        $this->createInstance();

        $this->collectionMock->expects(static::once())
            ->method('remove')
            ->with('key')
            ->willThrowException(new DocumentNotFoundException());

        static::assertFalse($this->client->delete('key'));
    }

    public function testDeleteHasItemThenReturnTrue(): void
    {
        $this->configureMock();
        $this->createInstance();

        $resultMock = $this->createMock(MutationResultImpl::class);

        $this->collectionMock->expects(static::once())
            ->method('remove')
            ->with('key')
            ->willReturn($resultMock);

        static::assertTrue($this->client->delete('key'));
    }

    public function testSet(): void
    {
        $this->configureMock();
        $this->createInstance();

        $resultMock = $this->createMock(StoreResultImpl::class);

        $options = new UpsertOptions();
        $options->expiry(10);

        $stored = ['name' => 'My Name', 'age' => 25];

        $this->collectionMock->expects(static::once())
            ->method('upsert')
            ->with('key', $stored, $options)
            ->willReturn($resultMock);

        static::assertEquals(
            $stored,
            $this->client->set('key', $stored, 10)
        );
    }

    public function testSetDateIntervalTtl(): void
    {
        $this->configureMock();
        $this->createInstance();

        $dateInterval = (new DateTime('2010-01-01 10:10:00'))
            ->diff(new DateTime('2010-01-01 10:10:20'));

        $resultMock = $this->createMock(StoreResultImpl::class);

        $options = new UpsertOptions();
        $options->expiry(20);

        $stored = ['name' => 'My Name', 'age' => 25];

        $this->collectionMock->expects(static::once())
            ->method('upsert')
            ->with('key', $stored, $options)
            ->willReturn($resultMock);

        static::assertEquals(
            $stored,
            $this->client->set('key', $stored, $dateInterval)
        );
    }

    public function testSetTtlGreaterThanThirtyDays(): void
    {
        $this->configureMock();
        $this->createInstance();


        $resultMock = $this->createMock(StoreResultImpl::class);

        $options = new UpsertOptions();
        $options->expiry(2592300);

        $stored = ['name' => 'My Name', 'age' => 25];

        $this->collectionMock->expects(static::once())
            ->method('upsert')
            ->with('key', $stored, $options)
            ->willReturn($resultMock);

        static::assertEquals(
            $stored,
            $this->client->set('key', $stored, 2592300)
        );
    }

    public function testFlush(): void
    {
        $this->configureMock();
        $this->createInstance();

        $bucketManagerMock = $this->createMock(BucketManager::class);

        $bucketManagerMock->expects(static::once())
            ->method('flush')
            ->with('bucketName')
            ->willReturn(true);

        $this->clusterMock->expects(static::once())
            ->method('buckets')
            ->willReturn($bucketManagerMock);

        static::assertTrue($this->client->flush());

    }

}