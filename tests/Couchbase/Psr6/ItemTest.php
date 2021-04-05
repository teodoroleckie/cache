<?php

namespace Tleckie\Cache\Tests\Couchbase\Psr6;

use Tleckie\Cache\Couchbase\Psr6\Item;
use Closure;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Class ItemTest
 *
 * @covers  \Tleckie\Cache\Couchbase\Psr6\Item
 * @package Tleckie\Cache\Tests\Couchbase\Psr6
 * @author  Teodoro Leckie Westberg <teodoroleckie@gmail.com>
 */
class ItemTest extends TestCase
{
    /** @var Item */
    private Item $item;

    public function setUp(): void
    {
        parent::setUp();

        $this->item = new Item('key');
    }

    public function testGetThenReturnNull(): void
    {
        static::assertNull($this->item->get());
        static::assertFalse($this->item->isHit());
    }

    public function testGetThenReturnNotNull(): void
    {
        /** @var Item $item */
        $item = Closure::bind(static function ($item) {
            $item->value = 'value';
            $item->isHit = true;

            return $item;

        }, null, Item::class)(new Item('key'));

        static::assertEquals('value', $item->get());
        static::assertTrue($item->isHit());
    }

    public function testSetValue(): void
    {
        static::assertNull($this->item->get());
        static::assertEquals('value', $this->item->set('value')->get());
    }

    public function testGeKey(): void
    {
        static::assertEquals('key', (new Item('key'))->getKey());
    }

    public function testExpiresAtNull(): void
    {
        $test = $this;

        /** @var Item $item */
        Closure::bind(static function ($item) use ($test) {

            $test->assertEquals(0, $item->ttl);

        }, null, Item::class)($this->item->expiresAt(null));
    }

    public function testExpiresAtNotNull(): void
    {
        $time = (new DateTime())->setTimestamp(time()+10);

        $test = $this;

        /** @var Item $item */
        Closure::bind(static function ($item) use ($test) {

            $test->assertEquals(10, $item->ttl);

        }, null, Item::class)($this->item->expiresAt($time));
    }

    public function testExpiresAfterNullValue(): void
    {
        $test = $this;

        /** @var Item $item */
        Closure::bind(static function ($item) use ($test) {

            $test->assertEquals(0, $item->ttl);

        }, null, Item::class)($this->item->expiresAfter(null));
    }

    public function testExpiresAfterIntValue(): void
    {
        $test = $this;

        /** @var Item $item */
        Closure::bind(static function ($item) use ($test) {

            $test->assertEquals(10, $item->ttl);

        }, null, Item::class)($this->item->expiresAfter(10));
    }

    public function testExpiresAfterDateIntervalValue(): void
    {
        $dateOne = new DateTime('2010-01-01 10:10:20');
        $dateTwo = new DateTime('2010-01-01 10:10:00');
        $interval = $dateTwo->diff($dateOne);

        $test = $this;

        /** @var Item $item */
        Closure::bind(static function ($item) use ($test) {

            $test->assertEquals(20, $item->ttl);

        }, null, Item::class)($this->item->expiresAfter($interval));
    }


}