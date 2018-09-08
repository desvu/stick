<?php

/**
 * This file is part of the eghojansu/stick library.
 *
 * (c) Eko Kurniawan <ekokurniawanbs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fal\Stick\Test\Security;

use Fal\Stick\App;
use Fal\Stick\Security\Auth;
use Fal\Stick\Security\AuthValidator;
use Fal\Stick\Security\InMemoryUserProvider;
use Fal\Stick\Security\PlainPasswordEncoder;
use Fal\Stick\Security\SimpleUser;
use Fal\Stick\Security\SimpleUserTransformer;
use PHPUnit\Framework\TestCase;

class AuthValidatorTest extends TestCase
{
    private $validator;
    private $auth;

    public function setUp()
    {
        $app = new App();
        $provider = new InMemoryUserProvider(new SimpleUserTransformer());
        $this->auth = new Auth($app, $provider, new PlainPasswordEncoder());

        $provider->addUser('foo', 'bar');
        $provider->addUser('bar', 'baz');

        $this->validator = new AuthValidator($this->auth);
    }

    private function createUser($username, $password)
    {
        return new SimpleUser('1', $username, $password);
    }

    public function testHas()
    {
        $this->assertTrue($this->validator->has('password'));
        $this->assertFalse($this->validator->has('foo'));
    }

    public function validatePasswordProvider()
    {
        return array(
            array(null, 'bar'),
            array($this->createUser('foo', 'bar'), 'bar'),
            array($this->createUser('foo', 'baz'), 'baz'),
            array($this->createUser('foo', 'bar'), 'baz', false),
        );
    }

    /**
     * @dataProvider validatePasswordProvider
     */
    public function testValidatePassword($user, $password, $expected = true)
    {
        $this->auth->setUser($user);

        $this->assertEquals($expected, $this->validator->validate('password', $password));
    }
}
