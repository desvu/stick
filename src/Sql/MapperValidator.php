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

namespace Fal\Stick\Sql;

use Fal\Stick\Core;
use Fal\Stick\Validation\ValidatorInterface;
use Fal\Stick\Validation\ValidatorTrait;

/**
 * Mapper related validator.
 *
 * @author Eko Kurniawan <ekokurniawanbs@gmail.com>
 */
class MapperValidator implements ValidatorInterface
{
    use ValidatorTrait;

    /**
     * @var Core
     */
    private $fw;

    /**
     * @var Connection
     */
    private $db;

    /**
     * Class constructor.
     *
     * @param Core         $fw
     * @param Connection $db
     */
    public function __construct(Core $fw, Connection $db)
    {
        $this->fw = $fw;
        $this->db = $db;
    }

    /**
     * Check if given value exists.
     *
     * @param mixed  $val
     * @param string $table
     * @param string $column
     *
     * @return bool
     */
    protected function _exists($val, $table, $column): bool
    {
        $mapper = new Mapper($this->fw, $this->db, $table);
        $mapper->load(array($column => $val), array('limit' => 1));

        return $mapper->valid();
    }

    /**
     * Check if given value is unique.
     *
     * @param mixed       $val
     * @param string      $table
     * @param string      $column
     * @param string|null $fid
     * @param mixed       $id
     *
     * @return bool
     */
    protected function _unique($val, $table, $column, $fid = null, $id = null): bool
    {
        $mapper = new Mapper($this->fw, $this->db, $table);
        $mapper->load(array($column => $val), array('limit' => 1));

        return $mapper->dry() || ($fid && (!$mapper->exists($fid) || $mapper->get($fid) == $id));
    }
}
