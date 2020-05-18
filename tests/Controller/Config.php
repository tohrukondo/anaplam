<?php

namespace Anaplam\Tests;

class Config
{
    public static $user = 'anaplam';
    public static $pass = 'anaplam-db-password';
    public static $api_key = 'a0fe578fda4e67b5d5175c2f777995f4';

    public static $base_uri = 'https://hiro1.anaplam.hiroshima-u.ac.jp';

    public static $master = 'hiro1.anaplam.hiroshima-u.ac.jp';
    public static $node1 = 'hiro2.anaplam.hiroshima-u.ac.jp';
    public static $node2 = 'tokyo2.anaplam.hiroshima-u.ac.jp';
    public static $node3 = 'ishikari2.anaplam.hiroshima-u.ac.jp';

    public static $account = 'nextech';

    public static function getNodes()
    {
        return [static::$node1, static::$node2, static::$node3];
    }
}
