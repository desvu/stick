<?php declare(strict_types=1);

/**
 * This file is part of the eghojansu/stick library.
 *
 * (c) Eko Kurniawan <ekokurniawanbs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fal\Stick\Sql;

use Fal\Stick\Helper;

class Mapper implements \ArrayAccess
{
    /** Pagination records perpage */
    const PERPAGE = 10;

    /** Event names */
    const EVENT_LOAD = 'load';
    const EVENT_BEFOREINSERT = 'beforeinsert';
    const EVENT_AFTERINSERT = 'afterinsert';
    const EVENT_INSERT = self::EVENT_AFTERINSERT;
    const EVENT_BEFOREUPDATE = 'beforeupdate';
    const EVENT_AFTERUPDATE = 'afterupdate';
    const EVENT_UPDATE = self::EVENT_AFTERUPDATE;
    const EVENT_BEFOREDELETE = 'beforedelete';
    const EVENT_AFTERDELETE = 'afterdelete';
    const EVENT_DELETE = self::EVENT_AFTERDELETE;

    /** @var Connection */
    protected $db;

    /** @var string */
    protected $driver;

    /** @var string */
    protected $table;

    /** @var string */
    protected $map;

    /** @var array */
    protected $fields;

    /** @var array Primary keys */
    protected $keys;

    /** @var array */
    protected $hive;

    /** @var array */
    protected $adhoc = [];

    /** @var array */
    protected $props = [];

    /** @var array */
    protected $triggers = [];

    public function __construct(Connection $db, string $table = null, array $fields = null, int $ttl = 60)
    {
        $this->db = $db;
        $this->hive = [
            'fields' => $fields,
            'ttl' => $ttl,
            'loaded' => false,
        ];
        $this->driver = $db->getDriver();
        $this->setTable($table ?? $this->table ?? Helper::snakecase(Helper::classname($this)));
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): Mapper
    {
        $use = $this->driver === Connection::DB_OCI ? strtoupper($table) : $table;
        $prev = $this->table;

        $this->table = $use;
        $this->map = $this->db->quotekey($use);
        $this->fields = $this->db->schema($use, $this->hive['fields'], $this->hive['ttl']);
        $this->reset();

        if (!$this->keys || ($prev && $this->table !== $prev)) {
            $this->keys = [];

            foreach ($this->fields as $key => $value) {
                if ($value['pkey']) {
                    $this->keys[] = $key;
                }
            }
        }

        return $this;
    }

    public function withTable(string $table, array $fields = null, int $ttl = 60): Mapper
    {
        return new self($this->db, $table, $fields, $ttl);
    }

    public function getFields(): array
    {
        return array_keys($this->fields + $this->adhoc);
    }

    public function getSchema(): array
    {
        return $this->fields;
    }

    public function getConnection(): Connection
    {
        return $this->db;
    }

    public function on($events, callable $trigger): Mapper
    {
        foreach (Helper::reqarr($events) as $event) {
            $this->triggers[$event][] = $trigger;
        }

        return $this;
    }

    public function trigger(string $event, array $args = []): bool
    {
        if (!isset($this->triggers[$event])) {
            return false;
        }

        foreach ($this->triggers[$event] as $func) {
            if (call_user_func_array($func, $args) === true) {
                return false;
            }
        }

        return true;
    }

    public function hasOne($target = null, string $targetId = null, string $refId = null, array $options = null): Relation
    {
        return $this->createRelation($target, $targetId, $refId, null, null, $options);
    }

    public function hasMany($target = null, string $targetId = null, string $refId = null, $pivot = null, array $options = null): Relation
    {
        return $this->createRelation($target, $targetId, $refId, $pivot, false, $options);
    }

    public function createRelation($target = null, string $targetId = null, string $refId = null, $pivot = null, bool $one = null, array $options = null): Relation
    {
        $use = $target;

        if (is_string($target)) {
            if (is_a($target, self::class, true) || is_subclass_of($target, self::class)) {
                $use = new $target($this->db);
            } elseif (class_exists($target)) {
                throw new \UnexpectedValueException('Target must be instance of ' . self::class . ' or a name of valid table, given ' . $target);
            } else {
                $use = $this->withTable($target);
            }
        } elseif ($target && !$target instanceof $this) {
            throw new \UnexpectedValueException('Target must be instance of ' . self::class . ' or a name of valid table, given ' . get_class($target));
        }

        return new Relation($this, $use, $targetId, $refId, $pivot, $one, $options);
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->fields + $this->adhoc);
    }

    public function &get(string $key)
    {
        if (array_key_exists($key, $this->fields)) {
            return $this->fields[$key]['value'];
        } elseif (array_key_exists($key, $this->adhoc)) {
            return $this->adhoc[$key]['value'];
        } elseif (array_key_exists($key, $this->props)) {
            return $this->props[$key]['value'];
        } elseif (method_exists($this, $key)) {
            $res = $this->$key();
            $this->props[$key]['self'] = true;
            $this->props[$key]['value'] =& $res;

            return $this->props[$key]['value'];
        }

        throw new \LogicException('Undefined field ' . $key);
    }

    public function set(string $key, $val): Mapper
    {
        if (array_key_exists($key, $this->fields)) {
            $val = is_null($val) && $this->fields[$key]['nullable'] ? $val : $this->db->value($this->fields[$key]['pdo_type'], $val);
            $this->fields[$key]['changed'] = $this->fields[$key]['initial'] !== $val || $this->fields[$key]['default'] !== $val;
            $this->fields[$key]['value'] = $val;
        } elseif (isset($this->adhoc[$key])) {
            // Adjust result on existing expressions
            $this->adhoc[$key]['value'] = $val;
        } elseif (is_string($val)) {
            // Parenthesize expression in case it's a subquery
            $this->adhoc[$key] = ['expr' => '(' . $val . ')', 'value' => null];
        } else {
            $this->props[$key] = ['self' => false, 'value' => $val];
        }

        return $this;
    }

    public function clear(string $key): Mapper
    {
        if (array_key_exists($key, $this->fields)) {
            $this->fields[$key]['value'] = $this->fields[$key]['initial'];
            $this->fields[$key]['changed'] = false;
        } elseif (array_key_exists($key, $this->adhoc)) {
            unset($this->adhoc[$key]);
        } else {
            unset($this->props[$key]);
        }

        return $this;
    }

    public function reset(): Mapper
    {
        foreach ($this->fields as &$field) {
            $field['value'] = $field['initial'] = $field['default'];
            $field['changed'] = false;
            unset($field);
        }

        foreach ($this->adhoc as &$field) {
            $field['value'] = null;
            unset($field);
        }

        foreach ($this->props as $key => $field) {
            if ($field['self']) {
                unset($this->props[$key]);
            }
        }

        $this->hive['loaded'] = false;

        return $this;
    }

    public function required(string $key): bool
    {
        return !($this->fields[$key]['nullable'] ?? true);
    }

    public function changed(string $key = null): bool
    {
        if ($key) {
            return $this->fields[$key]['changed'] ?? false;
        }

        foreach ($this->fields as $key => $value) {
            if ($value['changed']) {
                return true;
            }
        }

        return false;
    }

    public function keys(): array
    {
        $res = [];

        foreach ($this->keys as $key) {
            $res[$key] = $this->fields[$key]['initial'];
        }

        return $res;
    }

    public function getKeys(): array
    {
        return $this->keys;
    }

    public function setKeys(array $keys): Mapper
    {
        foreach ($keys as $key) {
            if (!isset($this->fields[$key])) {
                throw new \LogicException('Invalid key ' . $key);
            }
        }

        $this->keys = $keys;

        return $this;
    }

    public function fromArray(array $source, callable $func = null): Mapper
    {
        foreach ($func ? call_user_func_array($func, [$source]) : $source as $key => $value) {
            if (isset($this->fields[$key])) {
                $this->set($key, $value);
            }
        }

        return $this;
    }

    public function toArray(callable $func = null): array
    {
        $result = [];
        foreach ($this->fields + $this->adhoc + $this->props as $key => $value) {
            $result[$key] = $value['value'];
        }

        return $func ? call_user_func_array($func, [$result]) : $result;
    }

    public function valid(): bool
    {
        return $this->hive['loaded'];
    }

    public function dry(): bool
    {
        return !$this->hive['loaded'];
    }

    public function paginate(int $page = 1, $filter = null, array $options = null, int $ttl = 0): array
    {
        $use = (array) $options;
        $limit = $use['perpage'] ?? static::PERPAGE;
        $total = $this->count($filter, $options, $ttl);
        $pages = (int) ceil($total / $limit);
        $subset = [];
        $count = 0;
        $start = 0;
        $end = 0;

        if ($page > 0) {
            $offset = ($page - 1) * $limit;
            $subset = $this->findAll($filter, compact('limit', 'offset') + $use, $ttl);
            $count = count($subset);
            $start = $offset + 1;
            $end = $offset + $count;
        }

        return compact('subset', 'total', 'count', 'pages', 'page', 'start', 'end');
    }

    public function count($filter = null, array $options = null, int $ttl = 0): int
    {
        $fields = (in_array($this->driver, [Connection::DB_MSSQL, Connection::DB_DBLIB, Connection::DB_SQLSRV]) ? 'TOP 100 PERCENT ' : '') . '*' . $this->stringifyAdhoc();
        list($sql, $args) = $this->stringify($fields, $filter, $options);

        $sql = 'SELECT COUNT(*) AS ' . $this->db->quotekey('_rows') .
            ' FROM (' . $sql . ') AS ' . $this->db->quotekey('_temp');
        $res = $this->db->exec($sql, $args, $ttl);

        return (int) $res[0]['_rows'];
    }

    public function load($filter = null, array $options = null, int $ttl = 0): Mapper
    {
        $found = $this->reset()->findAll($filter, ['limit'=>1] + (array) $options, $ttl);

        if ($found) {
            $this->fields = $found[0]->fields;
            $this->adhoc = $found[0]->adhoc;
            $this->props = $found[0]->props;
            $this->hive = $found[0]->hive;
        }

        return $this;
    }

    public function find(...$ids): Mapper
    {
        $vcount = count($ids);
        $pcount = count($this->keys);

        if ($vcount !== $pcount) {
            throw new \ArgumentCountError(__METHOD__ . ' expect exactly ' . $pcount . ' arguments, ' . $vcount . ' given');
        }

        return $this->load(array_combine($this->keys, $ids));
    }

    public function findAll($filter = null, array $options = null, int $ttl = 0): array
    {
        $fields = isset($options['group']) && !in_array($this->driver, [Connection::DB_MYSQL, Connection::DB_SQLITE]) ? $options['group'] : implode(',', array_map([$this->db, 'quotekey'], array_keys($this->fields)));
        list($sql, $args) = $this->stringify($fields . $this->stringifyAdhoc(), $filter, $options);

        return array_map([$this, 'factory'], $this->db->exec($sql, $args, $ttl));
    }

    public function save(): Mapper
    {
        return $this->hive['loaded'] ? $this->update() : $this->insert();
    }

    public function insert(): Mapper
    {
        $args = [];
        $ctr = 0;
        $fields = '';
        $values = '';
        $filter = [];
        $ckeys = [];
        $inc = NULL;

        if ($this->trigger(self::EVENT_BEFOREINSERT, [$this, $this->keys()])) {
            return $this;
        }

        foreach ($this->fields as $key => $field) {
            if ($field['pkey']) {
                if (!$inc && $field['pdo_type'] == \PDO::PARAM_INT && empty($field['value']) && !$field['nullable']) {
                    $inc = $key;
                }

                $filter[$key] = $this->db->value($field['pdo_type'], $field['value']);
            }

            if ($field['changed'] && $key !== $inc) {
                $fields .= ',' . $this->db->quotekey($key);
                $values .= ',' . '?';
                $args[++$ctr] = [$field['value'], $field['pdo_type']];
                $ckeys[] = $key;
            }
        }

        if (!$fields) {
            return $this;
        }

        $sql = 'INSERT INTO ' . $this->map . ' (' . ltrim($fields, ',') . ') ' . 'VALUES (' . ltrim($values, ',') . ')';
        $prefix = in_array($this->driver, [Connection::DB_MSSQL, Connection::DB_DBLIB, Connection::DB_SQLSRV]) && array_intersect($this->keys, $ckeys)? 'SET IDENTITY_INSERT ' . $this->map . ' ON;' : '';
        $suffix = $this->driver === Connection::DB_PGSQL ? ' RETURNING ' . $this->db->quotekey(reset($this->keys)) : '';

        $lID = $this->db->exec($prefix . $sql . $suffix, $args);
        $id = $this->driver === Connection::DB_PGSQL && $lID ? $lID[0][reset($this->keys)] : $this->db->pdo()->lastinsertid();

        // Reload to obtain default and auto-increment field values
        if ($inc || $filter) {
            $this->load($inc ? [$inc => $this->db->value($this->fields[$inc]['pdo_type'], $id)] : $filter);
        }

        $this->trigger(self::EVENT_AFTERINSERT, [$this, $this->keys()]);

        return $this;
    }

    public function update(): Mapper
    {
        $args = [];
        $ctr = 0;
        $pairs = '';
        $filter = '';

        if ($this->trigger(self::EVENT_BEFOREUPDATE, [$this, $this->keys()])) {
            return $this;
        }

        foreach ($this->fields as $key => $field) {
            if ($field['changed']) {
                $pairs .= ($pairs ? ',' : '') . $this->db->quotekey($key) . '=?';
                $args[++$ctr] = [$field['value'], $field['pdo_type']];
            }
        }

        foreach ($this->keys as $key) {
            $filter .= ($filter ? ' AND ' : ' WHERE ') . $this->db->quotekey($key) . '=?';
            $args[++$ctr] = [$this->fields[$key]['initial'], $this->fields[$key]['pdo_type']];
        }

        if ($pairs) {
            $sql = 'UPDATE ' . $this->map . ' SET ' . $pairs . $filter;

            $this->db->exec($sql, $args);
        }

        if ($this->trigger(self::EVENT_AFTERUPDATE, [$this, $this->keys()])) {
            return $this;
        }

        // reset changed flag after calling afterupdate
        foreach ($this->fields as $key => &$field) {
            $field['initial'] = $field['value'];
            $field['changed'] = false;
            unset($field);
        }

        return $this;
    }

    public function delete($filter = null, bool $hayati = false): int
    {
        if ($filter) {
            if ($hayati) {
                $out = 0;

                foreach ($this->findAll($filter) as $mapper) {
                    $out += $mapper->delete();
                }

                return $out;
            }

            $args = $this->db->filter($filter);
            $sql = 'DELETE FROM ' . $this->map . ($args ? ' WHERE ' . array_shift($args) : '');

            return (int) $this->db->exec($sql, $args);
        }

        $args = [];
        $ctr = 0;
        $out = 0;

        foreach ($this->keys as $key) {
            $filter .= ($filter ? ' AND ' : '') . $this->db->quotekey($key) . '=?';
            $args[++$ctr] = [$this->fields[$key]['initial'], $this->fields[$key]['pdo_type']];
        }

        if ($this->trigger(self::EVENT_BEFOREDELETE, [$this, $this->keys()])) {
            return 0;
        }

        if ($filter) {
            $out = (int) $this->db->exec('DELETE FROM ' . $this->map . ' WHERE ' . $filter, $args);
        }

        $this->trigger(self::EVENT_BEFOREDELETE, [$this, $this->keys()]);
        $this->reset();

        return $out;
    }

    protected function stringify(string $fields, $filter = null, array $options = null): array
    {
        $use = ($options ?? []) + [
            'group' => null,
            'having' => null,
            'order' => null,
            'limit' => 0,
            'offset' => 0,
        ];

        $args = [];
        $sql = 'SELECT ' . $fields . ' FROM ' . $this->map;

        $f = $this->db->filter($filter);
        if ($f) {
            $sql .= ' WHERE ' . array_shift($f);
            $args = array_merge($args, $f);
        }

        if ($use['group']) {
            $sql .= ' GROUP BY ' . $use['group'];
        }

        $f = $this->db->filter($use['having']);
        if ($f) {
            $sql .= ' HAVING ' . array_shift($f);
            $args = array_merge($args, $f);
        }

        if ($use['order']) {
            $order = ' ORDER BY ' . $use['order'];
        }

        // SQL Server fixes
        // We skip this part to test
        // @codeCoverageIgnoreStart
        if (in_array($this->driver, [Connection::DB_MSSQL, Connection::DB_SQLSRV, Connection::DB_ODBC]) && ($use['limit'] || $use['offset'])) {
            // order by pkey when no ordering option was given
            if (!$use['order'] && $this->keys) {
                $order = ' ORDER BY ' . implode(',', array_map([$this->db, 'quotekey'], $this->keys));
            }

            $ofs = (int) $use['offset'];
            $lmt = (int) $use['limit'];

            if (strncmp($this->db->getVersion(), '11', 2) >= 0) {
                // SQL Server >= 2012
                $sql .= $order . ' OFFSET ' . $ofs . ' ROWS';

                if ($lmt) {
                    $sql .= ' FETCH NEXT ' . $lmt . ' ROWS ONLY';
                }
            } else {
                // Require primary keys or order clause
                // SQL Server 2008
                $sql = preg_replace('/SELECT/',
                    'SELECT '.
                    ($lmt > 0 ? 'TOP ' . ($ofs+$lmt) : '') . ' ROW_NUMBER() '.
                    'OVER (' . $order . ') AS rnum,', $sql . $order, 1
                );
                $sql = 'SELECT * FROM (' . $sql . ') x WHERE rnum > ' . $ofs;
            }
        } else {
            $sql .= ($order ?? '');

            if ($use['limit']) {
                $sql .= ' LIMIT ' . (int) $use['limit'];
            }

            if ($use['offset']) {
                $sql .= ' OFFSET ' . (int) $use['offset'];
            }
        }
        // @codeCoverageIgnoreEnd

        return [$sql, $args];
    }

    protected function stringifyAdhoc(): string
    {
        $res = '';

        foreach ($this->adhoc as $key => $field) {
            $res .= ',' . $field['expr'] . ' AS ' . $this->db->quotekey($key);
        }

        return $res;
    }

    protected function factory(array $row): Mapper
    {
        $mapper = clone $this;
        $mapper->reset();
        $mapper->hive['loaded'] = true;

        foreach ($row as $key => $val) {
            if (array_key_exists($key, $this->fields)) {
                $mapper->fields[$key]['value'] = $mapper->fields[$key]['initial'] = is_null($val) && $this->fields[$key]['nullable'] ? $val : $this->db->value($this->fields[$key]['pdo_type'], $val);
            } else {
                $mapper->adhoc[$key]['value'] = $val;
            }
        }

        $this->trigger(self::EVENT_LOAD, [$mapper]);

        return $mapper;
    }

    protected function fieldArgs(string $field, array $args): array
    {
        if ($args) {
            $first = array_shift($args);
            array_unshift($args, [$field => $first]);
        }

        return $args;
    }

    public function offsetExists($offset)
    {
        return $this->exists($offset);
    }

    public function &offsetGet($offset)
    {
        $ref =& $this->get($offset);

        return $ref;
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->clear($offset);
    }

    public function __call($method, array $args)
    {
        if (Helper::istartswith('get', $method)) {
            $field = Helper::snakecase(Helper::icutafter('get', $method));
            array_unshift($args, $field);

            return $this->get(...$args);
        } elseif (Helper::istartswith('findby', $method)) {
            $field = Helper::snakecase(Helper::icutafter('findby', $method));
            $args = $this->fieldArgs($field, $args);

            return $this->findAll(...$args);
        } elseif (Helper::istartswith('loadby', $method)) {
            $field = Helper::snakecase(Helper::icutafter('loadby', $method));
            $args = $this->fieldArgs($field, $args);

            return $this->load(...$args);
        }

        throw new \BadMethodCallException('Call to undefined method ' . static::class . '::' . $method);
    }
}