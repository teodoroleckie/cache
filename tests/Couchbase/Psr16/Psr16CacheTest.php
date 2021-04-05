<?php

namespace Tleckie\Cache\Tests\Couchbase\Psr16;

use Tleckie\Cache\Couchbase\Client;
use Tleckie\Cache\Couchbase\Psr16\Exception\InvalidArgumentException;
use Tleckie\Cache\Couchbase\Psr16\Psr16Cache;
use Couchbase\DocumentNotFoundException;
use Couchbase\BaseException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class Psr16CacheTest
 *
 * @covers Tleckie\Cache\Couchbase\Psr16\Psr16Cache
 * @package Tleckie\Cache\Tests\Couchbase\Psr16
 * @author  Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class Psr16CacheTest extends TestCase
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

    /** @var Psr16Cache */
    private Psr16Cache $psr16Cache;

    public function setUp(): void
    {
        parent::setUp();
        $this->clientMock = $this->createMock(Client::class);

        $this->createInstance();
    }

    private function createInstance(string $namespace = ''): void
    {
        $this->psr16Cache = new Psr16Cache(
            $this->clientMock,
            $namespace
        );
    }

    public function testInvalidKey(): void
    {
        $counter = [];
        foreach (static::INVALID_KEY as $invalidKey) {
            try {
                $this->psr16Cache->get($invalidKey);
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
                $counter[] = $this->psr16Cache->get($validKey);
            } catch (InvalidArgumentException $exception) {
            }
        }

        static::assertSameSize(
            static::VALID_KEY,
            $counter
        );
    }

    public function testGetInCache(): void
    {
        $this->createInstance('namespace.');

        $expected = ['name' => 'My Name', 'age' => 32];
        $this->clientMock->expects(static::once())
            ->method('get')
            ->with('namespace.test')
            ->willReturn($expected);

        static::assertEquals(
            $expected,
            $this->psr16Cache->get('test', 'defaultValue')
        );
    }

    public function testGetDefaultValue(): void
    {
        $this->clientMock->expects(static::once())
            ->method('get')
            ->with('test')
            ->willThrowException(new DocumentNotFoundException);

        static::assertEquals(
            'defaultValue',
            $this->psr16Cache->get('test', 'defaultValue')
        );
    }

    public function testClearThenReturnFalse(): void
    {
        $this->clientMock = $this->createMock(Client::class);

        $this->psr16Cache = new Psr16Cache(
            $this->clientMock,
            'withNamespace'
        );


        static::assertFalse($this->psr16Cache->clear());
    }

    public function testClearThenReturnTrue(): void
    {
        $this->clientMock = $this->createMock(Client::class);

        $this->psr16Cache = new Psr16Cache(
            $this->clientMock,
            ''
        );

        $this->clientMock->expects(static::once())
            ->method('flush')
            ->willReturn(true);


        static::assertTrue($this->psr16Cache->clear());
    }

    public function testGetMultiple(): void
    {
        $keys = ['test1', 'test2'];

        $expected = [
            'test1' => 'value1',
            'test2' => 'defaultValue'
        ];

        $this->clientMock->expects(static::exactly(2))
            ->method('get')
            ->willReturnCallback(static function ($argument) {
                if ('test1' === $argument) {
                    return 'value1';
                }

                if ('test2' === $argument) {
                    throw new DocumentNotFoundException();
                }
            });

        static::assertEquals(
            $expected,
            $this->psr16Cache->getMultiple($keys, 'defaultValue')
        );
    }

    public function testGetMultipleKeyIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->psr16Cache->getMultiple('test', 'default');
    }

    public function testGetMultipleKeyIsNotValidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->psr16Cache->getMultiple([':'], 'default');
    }

    public function testGetMultipleDocumentNotFound(): void
    {
        $this->clientMock->expects(static::once())
            ->method('get')
            ->with('test' )
            ->willThrowException(new DocumentNotFoundException);

        $item = $this->psr16Cache->getMultiple(['test'], 'default');
        static::assertEquals(['test'=>'default'],$item);
    }

    public function testSetMultipleKeyIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->psr16Cache->setMultiple('test');
    }

    public function testSetMultiple(): void
    {
        $this->clientMock->expects(static::once())
            ->method('set')
            ->with('test')
            ->willReturn(true);
        static::assertTrue($this->psr16Cache->setMultiple(['test' => 'value']));
    }

    public function testSetMultipleIsNotValidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->psr16Cache->setMultiple(['@' => 'value']);
    }

    public function testDeleteMultipleKeyIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->psr16Cache->deleteMultiple('test');
    }

    public function testDeleteMultipleNotValidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->psr16Cache->deleteMultiple([':']);
    }

    public function testSetMultipleThenReturnFalse(): void
    {
        $values = ['test1' => 'value1', 'test2' => 'value2'];

        $this->clientMock->expects(static::exactly(2))
            ->method('set')
            ->willReturnCallback(static function ($key) {
                if ('test1' === $key) {
                    return 'value1';
                }

                if ('test2' === $key) {
                    throw new BaseException();
                }
            });

        static::assertFalse($this->psr16Cache->setMultiple($values));
    }

    public function testSetMultipleThenReturnTrue(): void
    {
        $values = ['test1' => 'value1', 'test2' => 'value2'];

        $this->clientMock->expects(static::exactly(2))
            ->method('set')
            ->willReturnOnConsecutiveCalls(['value1','value2']);

        static::assertTrue($this->psr16Cache->setMultiple($values));
    }

    public function testDeleteMultipleThenReturnTrue(): void
    {
        $keys = ['test1', 'test2'];

        $this->clientMock->expects(static::exactly(2))
            ->method('delete')
            ->willReturn(true);

        static::assertTrue($this->psr16Cache->deleteMultiple($keys));
    }

    public function testDeleteMultipleThenReturnFalse(): void
    {
        $keys = ['test1', 'test2'];

        $this->clientMock->expects(static::exactly(2))
            ->method('delete')
            ->willReturnCallback(static function ($key) {
                if ('test1' === $key) {
                    return true;
                }

                if ('test2' === $key) {
                    return false;
                }
            });

        static::assertFalse(
            $this->psr16Cache->deleteMultiple($keys)
        );
    }

    public function testHasThenReturnFalse(): void
    {
        $this->clientMock->expects(static::once())
            ->method('has')
            ->willReturn(false);

        static::assertFalse(
            $this->psr16Cache->has('key')
        );
    }

    public function testHasThenReturnTrue(): void
    {
        $this->clientMock->expects(static::once())
            ->method('has')
            ->willReturn(true);

        static::assertTrue(
            $this->psr16Cache->has('key')
        );
    }


}