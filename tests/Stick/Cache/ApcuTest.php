<?php

/**
 * This file is part of the eghojansu/stick library.
 *
 * (c) Eko Kurniawan <ekokurniawanbs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Created at Jan 22, 2019 14:14
 */

declare(strict_types=1);

namespace Fal\Stick\Test\Cache;

use Fal\Stick\Cache\CacheItem;
use Fal\Stick\Cache\Apcu;
use PHPUnit\Framework\TestCase;

class ApcuTest extends TestCase
{
    private $cache;

    public function setup()
    {
        $this->cache = new Apcu();
    }

    public function testExists()
    {
        $this->assertFalse($this->cache->exists('foo'));
    }

    public function testGet()
    {
        $this->assertNull($this->cache->get('foo'));
    }

    public function testSet()
    {
        $this->assertTrue($this->cache->set('foo', new CacheItem('foo')));
        $this->assertEquals('foo', $this->cache->get('foo')->getValue());
        $this->cache->reset();
    }

    public function testClear()
    {
        $this->assertFalse($this->cache->clear('foo'));
    }

    public function testReset()
    {
        $this->assertEquals(0, $this->cache->reset());

        $this->assertTrue($this->cache->set('foo', new CacheItem('foo')));
        $this->assertTrue($this->cache->set('foo.bar', new CacheItem('foo')));
        $this->assertTrue($this->cache->set('bar.bar', new CacheItem('foo')));
        $this->assertEquals(2, $this->cache->reset('.bar'));
        $this->assertEquals(1, $this->cache->reset());
    }
}
