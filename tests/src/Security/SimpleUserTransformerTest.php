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

use Fal\Stick\Security\SimpleUser;
use Fal\Stick\Security\SimpleUserTransformer;
use PHPUnit\Framework\TestCase;

class SimpleUserTransformerTest extends TestCase
{
    private $transfomer;

    public function setUp()
    {
        $this->transfomer = new SimpleUserTransformer();
    }

    public function testTransform()
    {
        $user = $this->transfomer->transform(array(
            'id' => '1',
            'username' => 'foo',
            'password' => 'bar',
        ));
        $manual = new SimpleUser('1', 'foo', 'bar');

        $this->assertEquals($manual, $user);
    }
}
