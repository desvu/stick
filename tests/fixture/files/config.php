<?php

return array(
    'foo' => 'bar',
    'bar' => 'baz',
    'routes' => array(
        array('GET /', function () {
            return 'registered from config';
        }),
    ),
    'qux' => 'quux',
    'redirects' => array(
        array('GET /foo', '/'),
    ),
    'arr' => range(1, 3),
    'configs' => __DIR__.'/subconfig.php',
    'rules' => array(
        array('foo', 'DateTime'),
    ),
    'listeners' => array(
        array('foo', function () {
            return true;
        }),
    ),
    'listeners_once' => array(
        array('foo_once', function () {
            return true;
        }),
    ),
    'maps' => array(
        array('Foo', array(
            'GET map /map/path' => 'bar',
        )),
    ),
);
