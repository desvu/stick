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

namespace Fal\Stick\Library;

use Fal\Stick\Fw;
use Fal\Stick\HttpException;
use Fal\Stick\Library\Html\Form;
use Fal\Stick\Library\Security\Auth;
use Fal\Stick\Library\Sql\Mapper;
use Fal\Stick\Library\Template\Template;
use Fal\Stick\Magic;

/**
 * Logic for handling CRUD.
 *
 * @author Eko Kurniawan <ekokurniawanbs@gmail.com>
 */
class Crud extends Magic
{
    const STATE_LISTING = 'listing';
    const STATE_VIEW = 'view';
    const STATE_CREATE = 'create';
    const STATE_UPDATE = 'update';
    const STATE_DELETE = 'delete';
    const STATE_FORBIDDEN = 'forbidden';

    /**
     * @var Fw
     */
    protected $_fw;

    /**
     * @var Template
     */
    protected $_template;

    /**
     * @var Auth
     */
    protected $_auth;

    /**
     * @var array
     */
    protected $_data = array(
        'state' => null,
        'route' => null,
        'page' => null,
        'keyword' => null,
        'fields' => array(),
        'segments' => array(),
        'form' => null,
        'mapper' => null,
    );

    /**
     * @var array
     */
    protected $_options = array(
        'title' => null,
        'subtitle' => null,
        'form' => null,
        'form_options' => null,
        'on_form_build' => null,
        'field_orders' => null,
        'field_labels' => null,
        'mapper' => null,
        'state' => null,
        'filters' => array(),
        'listing_options' => null,
        'searchable' => null,
        'segments' => null,
        'sid_start' => 1,
        'sid_end' => 1,
        'page' => null,
        'page_query_name' => 'page',
        'keyword' => null,
        'keyword_query_name' => 'keyword',
        'route' => null,
        'route_args' => null,
        'created_message' => 'Data has been created.',
        'updated_message' => 'Data has been updated.',
        'deleted_message' => 'Data has been deleted.',
        'created_message_key' => 'SESSION.alerts.success',
        'updated_message_key' => 'SESSION.alerts.info',
        'deleted_message_key' => 'SESSION.alerts.warning',
        'varname' => 'crud',
        'on_init' => null,
        'on_prepare_data' => null,
        'on_load' => null,
        'before_create' => null,
        'after_create' => null,
        'before_update' => null,
        'after_update' => null,
        'before_delete' => null,
        'after_delete' => null,
        'states' => null,
        'views' => null,
        'fields' => null,
        'roles' => null,
        'create_new' => false,
        'create_new_label' => null,
        'create_new_session_key' => 'SESSION.crud_create_new',
    );

    /**
     * @var array
     */
    protected $_funcs = array();

    /**
     * Class constructor.
     *
     * @param Fw       $app
     * @param Template $template
     * @param Auth     $auth
     */
    public function __construct(Fw $fw, Template $template, Auth $auth)
    {
        $states = array(
            static::STATE_LISTING,
            static::STATE_VIEW,
            static::STATE_CREATE,
            static::STATE_UPDATE,
            static::STATE_DELETE,
        );
        $nullStates = array_fill_keys($states, null);

        $this->_fw = $fw;
        $this->_template = $template;
        $this->_auth = $auth;

        $this->_options['states'] = array_fill_keys($states, true);
        $this->_options['views'] = $nullStates;
        $this->_options['fields'] = $nullStates;
        $this->_options['roles'] = $nullStates;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->_data);
    }

    /**
     * {@inheritdoc}
     */
    public function &get(string $key)
    {
        if (!$this->exists($key)) {
            $this->_data[$key] = null;
        }

        return $this->_data[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $val): Magic
    {
        $this->_data[$key] = $val;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(string $key): Magic
    {
        unset($this->_data[$key]);

        return $this;
    }

    /**
     * Register function.
     *
     * @param string   $name
     * @param callable $cb
     *
     * @return Crud
     */
    public function addFunction(string $name, callable $cb): Crud
    {
        $this->_funcs[$name] = $cb;

        return $this;
    }

    /**
     * Check functions.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasFunction(string $name): bool
    {
        return isset($this->_funcs[$name]);
    }

    /**
     * Enable state.
     *
     * @param string|array $states
     *
     * @return Crud
     */
    public function enable($states): Crud
    {
        $this->_options['states'] = array_fill_keys($this->arr($states), true) + $this->_options['states'];

        return $this;
    }

    /**
     * Disable state.
     *
     * @param string|array $states
     *
     * @return Crud
     */
    public function disable($states): Crud
    {
        $this->_options['states'] = array_fill_keys($this->arr($states), false) + $this->_options['states'];

        return $this;
    }

    /**
     * Sets fields for state.
     *
     * @param string|array $states
     * @param mixed        $fields
     *
     * @return Crud
     */
    public function field($states, $fields): Crud
    {
        $this->_options['fields'] = array_fill_keys($this->arr($states), $fields) + $this->_options['fields'];

        return $this;
    }

    /**
     * Sets view for state.
     *
     * @param string $state
     * @param string $view
     *
     * @return Crud
     */
    public function view(string $state, string $view): Crud
    {
        $this->_options['views'][$state] = $view;

        return $this;
    }

    /**
     * Sets roles for state.
     *
     * @param string $state
     * @param string $roles
     *
     * @return Crud
     */
    public function role(string $state, string $roles): Crud
    {
        $this->_options['roles'][$state] = $roles;

        return $this;
    }

    /**
     * Returns option value.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function option(string $name)
    {
        return $this->_options[$name] ?? null;
    }

    /**
     * Returns options.
     *
     * @return array
     */
    public function options(): array
    {
        return $this->_options;
    }

    /**
     * Returns data.
     *
     * @return array
     */
    public function data(): array
    {
        return $this->_data;
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
        if (empty($this->_data['route'])) {
            throw new \LogicException('No route defined.');
        }

        $args = is_string($path) ? explode('/', $path) : $path;

        return $this->_fw->path($this->_data['route'], $args, $query);
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
        $enabled = $this->_options['states'][$state] ?? false;
        $roles = $this->arr($this->_options['roles'][$state] ?? null);

        return $enabled && (!$roles || $this->_auth->isGranted(...$roles));
    }

    /**
     * Do render.
     *
     * @return string|null
     */
    public function render(): ?string
    {
        $this->init();

        $state = $this->_data['state'];
        $enabled = $this->_options['states'][$state] ?? false;
        $roles = $this->_options['roles'][$state] ?? null;
        $var = $this->_options['varname'];

        if ($enabled && (!$roles || $this->_auth->isGranted($roles))) {
            $handle = 'state'.$state;
            $view = $this->_options['views'][$state] ?? null;

            $this->prepareFields();
            $this->trigger('on_init');

            $out = $this->$handle();
        } else {
            $view = $this->_options['views'][static::STATE_FORBIDDEN] ?? null;
            $out = true;
        }

        if (empty($view)) {
            throw new \LogicException('No view for state: "'.$state.'".');
        }

        return $out ? $this->_template->render($view, array($var => $this)) : null;
    }

    /**
     * Prepare crud.
     */
    protected function init(): void
    {
        $route = $this->_options['route'] ?? $this->_fw->get('ALIAS');
        $segments = $this->_options['segments'] ?? array();

        if (empty($route)) {
            throw new \LogicException('No route defined.');
        }

        if (empty($this->_options['mapper'])) {
            throw new \LogicException('No mapper provided.');
        }

        $this->loadMapper();
        $this->loadForm();

        if (is_string($segments)) {
            $segments = explode('/', $segments);
        }

        if (empty($segments) || 'index' === $segments[0]) {
            $state = self::STATE_LISTING;
        } else {
            $state = $segments[0];
        }

        $this->_data['state'] = $state;
        $this->_data['route'] = $route;
        $this->_data['segments'] = $segments;
        $this->_data['keyword'] = $this->_options['keyword'] ?? $this->_fw->get('GET.'.$this->_options['keyword_query_name']) ?? null;
        $this->_data['page'] = (int) ($this->_options['page'] ?? $this->_fw->get('GET.'.$this->_options['page_query_name']) ?? 1);
        $this->_data['searchable'] = $this->_options['searchable'];
        $this->_data['route_args'] = $this->_options['route_args'];
        $this->_data['page_query_name'] = $this->_options['page_query_name'];
        $this->_data['keyword_query_name'] = $this->_options['keyword_query_name'];

        if (empty($this->_data['title'])) {
            $this->_data['title'] = 'Manage '.Str::titleCase($this->_data['mapper']->table());
        }

        if (empty($this->_data['subtitle'])) {
            $this->_data['subtitle'] = Str::titleCase($state);
        }
    }

    /**
     * Do go back and set message.
     *
     * @param string $key
     * @param string $messageKey
     *
     * @return bool
     */
    protected function goBack(string $key, string $messageKey): bool
    {
        $var = $this->_options[$key];
        $createNewKey = $this->_options['create_new_session_key'];
        $message = strtr($this->_options[$messageKey.'_message'] ?? '', array(
            '%table%' => $this->_data['mapper']->table(),
            '%id%' => implode(', ', $this->_data['mapper']->keys()),
        ));

        if ($this->_options['create_new'] && $this->_data['form']->create_new && 'create' === $this->_data['state']) {
            $createNew = true;
            $target = null;
        } else {
            $createNew = false;
            $target = array(
                $this->_data['route'],
                array_merge((array) $this->_options['route_args'], array('index')),
                array_filter(array(
                    $this->_options['page_query_name'] => $this->_data['page'],
                    $this->_options['keyword_query_name'] => $this->_data['keyword'],
                ), 'is_scalar'),
            );
        }

        $this->_fw
            ->set($var, $message)
            ->set($createNewKey, $createNew)
            ->reroute($target)
        ;

        return false;
    }

    /**
     * Load mapper.
     */
    protected function loadMapper(): void
    {
        $map = $this->_options['mapper'];

        if ($map instanceof Mapper) {
            $this->_data['mapper'] = $map;
        } elseif (class_exists($map)) {
            $this->_data['mapper'] = $this->_fw->service($map);
        } else {
            $this->_data['mapper'] = $this->_fw->instance(Mapper::class, array($map));
        }
    }

    /**
     * Load form.
     */
    protected function loadForm(): void
    {
        $form = $this->_options['form'];

        if ($form instanceof Form) {
            $this->_data['form'] = $form;
        } elseif ($form && class_exists($form)) {
            $this->_data['form'] = $this->_fw->service($form);
        } else {
            $this->_data['form'] = $this->_fw->instance(Form::class);
            $this->trigger('on_form_build', array($this->_data['form']));
        }
    }

    /**
     * Prepare fields, ensure field has label and name member.
     */
    protected function prepareFields(): void
    {
        $fields = $this->_options['fields'][$this->_data['state']] ?? null;

        if ($fields) {
            if (is_string($fields)) {
                $fields = array_fill_keys($this->arr($fields), null);
            }
        } else {
            $fields = $this->_data['mapper']->schema();
        }

        $orders = $this->arr($this->_options['field_orders']);
        $keys = array_unique(array_merge($orders, array_keys($fields)));
        $this->_data['fields'] = array_fill_keys($keys, array());

        foreach ($fields as $name => $field) {
            $default = array(
                'name' => $name,
                'label' => $this->_fw->trans($name, null, Str::titleCase($name)),
            );
            $this->_data['fields'][$name] = ((array) $field) + $default;
        }
    }

    /**
     * Prepare listing filters.
     *
     * @return array
     */
    protected function prepareFilters(): array
    {
        $keyword = $this->_data['keyword'];
        $filters = $this->_options['filters'];

        foreach ($keyword ? $this->arr($this->_options['searchable']) : array() as $field) {
            $filters[$field] = Str::endswith($field, '~') ? '%'.$keyword.'%' : $keyword;
        }

        return $filters;
    }

    /**
     * Prepare item filters.
     *
     * @return array
     */
    protected function prepareItemFilters(): array
    {
        $ids = array_slice($this->_data['segments'], $this->_options['sid_start'], $this->_options['sid_end']);
        $keys = $this->_data['mapper']->keys(false);

        if (count($ids) !== count($keys)) {
            throw new \LogicException('Insufficient primary keys!');
        }

        return $this->_options['filters'] + array_combine($keys, $ids);
    }

    /**
     * Trigger internal event.
     *
     * @param string     $eventName
     * @param array|null $args
     *
     * @return mixed
     */
    protected function trigger(string $eventName, array $args = null)
    {
        $cb = $this->_options[$eventName] ?? null;

        return is_callable($cb) ? $this->_fw->call($cb, $args) : null;
    }

    /**
     * Prepare form.
     *
     * @return Form
     */
    protected function prepareForm(): Form
    {
        $values = array_filter($this->_data['mapper']->toArray(), 'is_scalar');
        $initial = $this->_data['mapper']->toArray('initial');
        $data = ((array) $this->trigger('on_prepare_data')) + $values + $initial;
        $options = $this->_options['form_options'];

        if (is_callable($options)) {
            $options = $this->_fw->call($options);
        }

        $this->_data['form']->build((array) $options, $data);

        if ($this->_options['create_new'] && 'create' === $this->_data['state'] && !$this->_data['form']->fieldExists('create_new')) {
            $this->_data['form']->add('create_new', 'checkbox', array(
                'label' => $this->_options['create_new_label'],
                'attr' => array('checked' => $this->_fw->get($this->_options['create_new_session_key'])),
            ));
        }

        return $this->_data['form'];
    }

    /**
     * Normalize fields definitions.
     *
     * @param mixed $val
     *
     * @return array
     */
    protected function arr($val): array
    {
        return is_array($val) ? $val : array_filter(array_map('trim', explode(',', (string) $val)));
    }

    /**
     * Perform state listing.
     *
     * @return bool
     */
    protected function stateListing(): bool
    {
        $this->_data['data'] = $this->_data['mapper']->paginate(...array(
            $this->_data['page'],
            $this->prepareFilters(),
            $this->_options['listing_options'],
        ));

        return true;
    }

    /**
     * Perform state view.
     *
     * @return bool
     */
    protected function stateView(): bool
    {
        $this->_data['mapper']->load($this->prepareItemFilters());
        $this->trigger('on_load');

        if ($this->_data['mapper']->dry()) {
            throw new HttpException(null, 404);
        }

        return true;
    }

    /**
     * Perform state create.
     *
     * @return bool
     */
    protected function stateCreate(): bool
    {
        $form = $this->prepareForm();

        if ($form->isSubmitted() && $form->valid()) {
            $data = (array) $this->trigger('before_create');
            $this->_data['mapper']->fromArray($data + $form->getData())->save();
            $this->trigger('after_create');

            return $this->goBack('created_message_key', 'created');
        }

        return true;
    }

    /**
     * Perform state update.
     *
     * @return bool
     */
    protected function stateUpdate(): bool
    {
        $this->stateView();

        $form = $this->prepareForm();

        if ($form->isSubmitted() && $form->valid()) {
            $data = (array) $this->trigger('before_update');
            $this->_data['mapper']->fromArray($data + $form->getData())->save();
            $this->trigger('after_update');

            return $this->goBack('updated_message_key', 'updated');
        }

        return true;
    }

    /**
     * Perform state delete.
     *
     * @return bool
     */
    protected function stateDelete(): bool
    {
        $this->stateView();

        if ('POST' === $this->_fw->get('VERB')) {
            $this->trigger('before_delete');
            $this->_data['mapper']->delete();
            $this->trigger('after_delete');

            return $this->goBack('deleted_message_key', 'deleted');
        }

        return true;
    }

    /**
     * Setting option via method call or call registered function.
     *
     * @param string $option
     * @param array  $args
     *
     * @return mixed
     */
    public function __call($option, $args)
    {
        if (isset($this->_funcs[$option])) {
            return $this->_fw->call($this->_funcs[$option], $args);
        }

        if ($args) {
            $name = Str::snakeCase($option);

            if (array_key_exists($name, $this->_options)) {
                $value = $args[0];

                if (is_array($this->_options[$name])) {
                    if (!is_array($value)) {
                        throw new \UnexpectedValueException('Option "'.$name.'" expect array value.');
                    }

                    $this->_options[$name] = array_replace($this->_options[$name], $value);
                } else {
                    $this->_options[$name] = $value;
                }
            }
        }

        return $this;
    }
}