<?php declare(strict_types=1);

/**
 * This file is part of the eghojansu/stick library.
 *
 * (c) Eko Kurniawan <ekokurniawanbs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fal\Stick\Security;

use Fal\Stick\Database\Sql\Sql;

class SqlUserProvider implements UserProviderInterface
{
    /** @var Sql */
    protected $db;

    /** @var UserTransformerInterface */
    protected $transformer;

    /** @var array */
    protected $option;

    /**
     * Class constructor
     *
     * @param Sql                      $db
     * @param callable                 $transformer
     * @param UserTransformerInterface $option
     */
    public function __construct(
        Sql $db,
        UserTransformerInterface $transformer,
        array $option = []
    ) {
        $this->db = $db;
        $this->transformer = $transformer;
        $this->setOption($option);
    }

    /**
     * Get option
     *
     * @return array
     */
    public function getOption(): array
    {
        return $this->option;
    }

    /**
     * Set option
     *
     * @param  array $option
     *
     * @return SqlUserProvider
     */
    public function setOption(array $option): SqlUserProvider
    {
        $this->option = $option + [
            'table' => 'user',
            'username' => 'username',
            'id' => 'id'
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function findByUsername(string $username): ?UserInterface
    {
        return $this->transform($this->option['username'], $username);
    }

    /**
     * {@inheritdoc}
     */
    public function findById(string $id): ?UserInterface
    {
        return $this->transform($this->option['id'], $id);
    }

    /**
     * Transform record to UserInterface
     *
     * @param  string  $key
     * @param  scalar  $val
     *
     * @return UserInterface|null
     */
    protected function transform(string $key, $val): ?UserInterface
    {
        $user = $this->db->exec(
            'SELECT * FROM ' . $this->db->quotekey($this->option['table']) .
            ' WHERE ' . $this->db->quotekey($key) . ' = ? LIMIT 1',
            [$val]
        );

        return $user ? $this->transformer->transform($user[0]) : null;
    }
}
