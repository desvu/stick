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

namespace Fal\Stick\Library\Crud;

use Fal\Stick\App;
use Fal\Stick\Library\Security\Auth;
use Fal\Stick\Library\Sql\Mapper;
use Fal\Stick\Magic;

/**
 * Crud data holder.
 *
 * @author Eko Kurniawan <ekokurniawanbs@gmail.com>
 */
class CrudData extends Magic
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var Auth
     */
    private $auth;

    /**
     * @var string
     */
    private $route;

    /**
     * @var array
     */
    private $roles;

    /**
     * @var array
     */
    private $data;

    /**
     * @var callable
     */
    private $displayer;

    /**
     * Class constructor.
     *
     * @param App           $app
     * @param Auth          $auth
     * @param string        $route
     * @param array         $roles
     * @param array         $data
     * @param callable|null $displayer
     */
    public function __construct(App $app, Auth $auth, string $route, array $roles, array $data, callable $displayer = null)
    {
        $this->app = $app;
        $this->auth = $auth;
        $this->route = $route;
        $this->roles = $roles;
        $this->data = $data;
        $this->displayer = $displayer;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function &get(string $key)
    {
        if (!$this->exists($key)) {
            $this->data[$key] = null;
        }

        return $this->data[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $val): Magic
    {
        throw new \LogicException('Crud data is frozen.');
    }

    /**
     * {@inheritdoc}
     */
    public function clear(string $key): Magic
    {
        throw new \LogicException('Crud data is frozen.');
    }

    /**
     * Returns crud link.
     *
     * @param mixed $path
     * @param mixed $query
     *
     * @return string
     */
    public function path($path = 'index', $query = null): string
    {
        $args = is_string($path) ? explode('/', $path) : $path;

        return $this->app->path($this->route, $args, $query);
    }

    /**
     * Returns display for item field.
     *
     * @param string $field
     * @param Mapper $item
     *
     * @return mixed
     */
    public function display(string $field, Mapper $item = null)
    {
        if (is_callable($this->displayer)) {
            return $this->app->call($this->displayer, array($field, $item));
        }

        return $item[$field] ?? null;
    }

    /**
     * Returns true if state roles not exists or role is granted.
     *
     * @param string $state
     *
     * @return bool
     */
    public function isGranted(string $state): bool
    {
        $roles = $this->roles[$state] ?? null;

        return !$roles || $this->auth->isGranted($roles);
    }
}