<?php

namespace Tleckie\Cache\Tests\Infrastructure\Couchbase\Psr6;


use Tleckie\Cache\Infrastructure\Couchbase\Client;
use Tleckie\Cache\Infrastructure\Couchbase\Psr6\Exception\InvalidArgumentException;
use Tleckie\Cache\Infrastructure\Couchbase\Psr6\Item;
use Tleckie\Cache\Infrastructure\Couchbase\Psr6\Pool;
use Couchbase\BaseException;
use Couchbase\StoreResultImpl;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class PoolTest
 *
 * @covers  \Tleckie\Cache\Infrastructure\Couchbase\Psr6\Pool
 * @package Tleckie\Cache\Tests\Infrastructure\Couchbase\Psr6
 * @author  Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class PoolTest extends TestCase
{
    private const INVALID_KEY = [
        ':',
        'test{',
        'test}',
        'test(',
        'test)',
        'test:',
        'test/',
        'test@',
        '',
        'testlargesize-testlargesize-testlargesize-testlargesize-testlarge-testlarge',
    ];

    private const VALID_KEY = [
        '_.#?',
        '0',
        'valid_key#valid_key*valid_key',
        'testlargesize-testlargesize-testlargesize-testlargesize-testlarge'
    ];

    /** @var Client|MockObject */
    private Client|MockObject $clientMock;

    /** @var Pool */
    private Pool $pool;

    public function setUp(): void
    {
        parent::setUp();

        $this->clientMock = $this->createMock(Client::class);

        $this->createInstance();
    }

    private function createInstance(string $namespace = ''): void
    {
        $this->pool = new Pool(
            $this->clientMock,
            $namespace
        );
    }

    public function testInvalidKey(): void
    {
        $counter = [];
        foreach (static::INVALID_KEY as $invalidKey) {
            try {
                $this->pool->getItem($invalidKey);
            } catch (InvalidArgumentException $exception) {
                $counter[] = $exception;
            }
        }

        static::assertSameSize(
            static::INVALID_KEY,
            $counter
        );
    }

    public function testValidKey(): void
    {
        $counter = [];
        foreach (static::VALID_KEY as $validKey) {
            try {
                $counter[] = $this->pool->getItem($validKey);
            } catch (InvalidArgumentException $exception) {
            }
        }

        static::assertSameSize(
            static::VALID_KEY,
            $counter
        );
    }

    public function testGetItems(): void
    {
        $this->createInstance('namespace.');
        $this->clientMock->expects(static::exactly(2))
            ->method('get')
            ->withConsecutive(['namespace.key1'], ['namespace.key2'])
            ->willReturnOnConsecutiveCalls('CacheValue', 'CacheValue2');

        /** @var Item[] $items */
        $items = $this->pool->getItems(['key1', 'key2']);

        static::assertEquals('CacheValue', $items['key1']->get());
        static::assertTrue( $items['key1']->isHit());
    }

    public function testGetItemsInvalidNamespace(): void
    {
        try{
            $this->createInstance(':');
            $this->pool->getItems(['key1', 'key2']);

        }catch (InvalidArgumentException $exception){
            static::assertEquals('Invalid key ":key1" provided', $exception->getMessage());
        }
    }

    public function testGetItemsInvalidKey(): void
    {
        try{
            $this->createInstance('namespace.');
            $this->pool->getItems([':', 'key2']);

        }catch (InvalidArgumentException $exception){
            static::assertEquals('Invalid key "namespace.:" provided', $exception->getMessage());
        }
    }

    public function testClearThenReturnTrue(): void
    {
        $this->createInstance();

        $this->clientMock->expects(static::once())
            ->method('flush')
            ->willReturn(true);

        static::assertTrue($this->pool->clear());
    }

    public function testClearThenReturnFalse(): void
    {
        $this->createInstance('namespace.');

        static::assertFalse($this->pool->clear());
    }

    public function testDeleteItemsThenReturnTrue(): void
    {
        $this->createInstance('namespace.');
        $this->clientMock->expects(static::exactly(2))
            ->method('delete')
            ->withConsecutive(['namespace.key1'], ['namespace.key2'])
            ->willReturnOnConsecutiveCalls(true, true);

        static::assertTrue($this->pool->deleteItems(['key1','key2']));
    }

    public function testGetItemThrowBaseException(): void
    {
        $this->clientMock->expects(static::once())
            ->method('get')
            ->with('test')
            ->willThrowException(new BaseException);

        $item = $this->pool->getItem('test');

        static::assertFalse($item->isHit());
    }

    public function testCommit(): void
    {
        $this->createInstance('namespace.');
        $resultMock = $this->createMock(StoreResultImpl::class);

        $this->clientMock->expects(static::once())
            ->method('set')
            ->with('namespace.key')
            ->willReturn($resultMock);

        $item = (new Item('key'))->set('value');

        static::assertTrue($this->pool->saveDeferred($item));

        static::assertTrue($this->pool->commit());
        static::assertTrue($item->isHit());
    }

    public function testCommitInvalidKeyThenReturnFalse(): void
    {
        $item = (new Item(':'))->set('value');

        $this->pool->saveDeferred($item);

        static::assertFalse($this->pool->commit());
    }

    public function testCommitNotSavedThenReturnFalse(): void
    {
        $item = (new Item('key'))->set('value');

        $this->pool->saveDeferred($item);

        $this->clientMock->expects(static::once())
            ->method('set')
            ->with('key')
            ->willThrowException(new BaseException);

        static::assertFalse($this->pool->commit());
        static::assertFalse($item->isHit());
    }

    public function testHasItem(): void
    {
        $this->createInstance('namespace.');
        $this->clientMock->expects(static::once())
            ->method('has')
            ->with('namespace.key')
            ->willReturn(true);

        static::assertTrue($this->pool->hasItem('key'));
    }

}