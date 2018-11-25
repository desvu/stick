<?php

/**
 * This file is part of the eghojansu/stick library.
 *
 * (c) Eko Kurniawan <ekokurniawanbs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fal\Stick\Test\Sql;

use Fal\Stick\Fw;
use Fal\Stick\Sql\Connection;
use Fal\Stick\Sql\Mapper;
use Fal\Stick\Sql\MapperParameterConverter;
use Fixture\Mapper\TFriends;
use Fixture\Mapper\TUser;
use PHPUnit\Framework\TestCase;

class MapperParameterConverterTest extends TestCase
{
    private $converter;
    private $fw;

    private function prepare($handler, array $params)
    {
        $this->fw = new Fw();
        $conn = new Connection($this->fw, 'sqlite::memory:', null, null, array(file_get_contents(FIXTURE.'files/schema.sql')));

        $conn->getPdo()->exec('insert into user (username) values ("foo"), ("bar"), ("baz")');
        $conn->getPdo()->exec('insert into friends (user_id, friend_id, level) values (1, 2, 3), (2, 3, 4)');

        $this->fw->setRule(Connection::class, $conn);

        $this->converter = new MapperParameterConverter($this->fw, $handler, $params);
    }

    /**
     * @dataProvider getArgs
     */
    public function testResolve($expected, $handler, $params)
    {
        $this->prepare($handler, $params);

        $this->assertEquals($expected, $this->converter->resolve());
    }

    public function testResolveMapper()
    {
        $handler = function (TUser $foo) {};
        $params = array('foo' => 1);
        $this->prepare($handler, $params);

        $args = $this->converter->resolve();

        $this->assertCount(1, $args);
        $this->assertInstanceOf(Mapper::class, $args['foo']);
        $this->assertEquals('foo', $args['foo']->get('username'));
    }

    public function testResolveMapperComposit()
    {
        $handler = function (TFriends $foo) {};
        $params = array('foo' => 1, 'bar' => 2);
        $this->prepare($handler, $params);

        $args = $this->converter->resolve();

        $this->assertCount(1, $args);
        $this->assertInstanceOf(Mapper::class, $args['foo']);
        $this->assertEquals(3, $args['foo']->get('level'));
    }

    public function testResolveMapperOverflowParams()
    {
        $handler = function (TUser $foo) {};
        $params = array('foo' => 1, 2 /* overflow */);
        $this->prepare($handler, $params);

        $args = $this->converter->resolve();

        $this->assertCount(2, $args);
        $this->assertInstanceOf(Mapper::class, $args['foo']);
        $this->assertEquals('foo', $args['foo']->get('username'));
    }

    /**
     * @expectedException \Fal\Stick\HttpException
     * @expectedExceptionMessage Record of user is not found.
     */
    public function testResolveMapperException()
    {
        $handler = function (TUser $foo) {};
        $params = array('foo' => 4);
        $this->prepare($handler, $params);

        $this->converter->resolve();
    }

    public function getArgs()
    {
        return array(
            array(array(), function () {}, array()),
            array(array('foo' => 'bar'), function ($foo) {}, array('foo' => 'bar')),
            array(array('foo' => 'bar', 'bar' => 'baz'), function ($foo, $bar) {}, array('foo' => 'bar', 'bar' => 'baz')),
            array(array('foo' => 'bar'), function ($foo, $bar) {}, array('foo' => 'bar')),
        );
    }
}