<?php

namespace Anaplam\Tests;

use Anaplam\Controller\RulesFlowController;
require_once 'Config.php';
require_once 'ModuleTest.php';

class EnvTest extends BaseTestCase
{
    // env

    public static $env1 = [
        'mode' => 'low-latency',
        'rtt' => 51,
    ];
    public static $env2 = [
        'mode' => 'lb',
        'rtt' => 0,
    ];
    public static $env3 = [
        'mode' => 'low-latency',
        'rtt' => 0,
    ];
    public static $env_cl = [
        'mode' => 'cl',
    ];
    public static $env_no_mode = [
        'rtt' => 51,
    ];
    public static $env_rtt_ill = [
        'mode' => 'low-latency',
        'rtt' => 'illegal',
    ];

    public function test()
    {
        $this->assertTrue(true);
    }
}

