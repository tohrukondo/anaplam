<?php

namespace Anaplam\Tests;

use Anaplam\Controller\RulesFlowController;
require_once 'Config.php';
require_once 'BaseTestCase.php';

class RuleTest extends BaseTestCase
{
    protected static $function = 'rules';

   // rule.

    public static function getRuleDefault()
    {
        return [ 'name' => 'default', 'flow' => 'typeA' ];
    }
    public static function getRuleDefaultTypeA()
    {
        return [ 'name' => 'default', 'flow' => 'typeA' ];
    }
    public static function getRuleDefaultTypeB()
    {
        return [ 'name' => 'default', 'flow' => 'typeB' ];
    }
    public static function getRuleLL()
    {
        return [
            'name' => 'low-latency',
            'conditions' => [
                "env.mode == 'low-latency'",
                "env.rtt > 50",
            ],
            'flow' => 'typeA',
        ];
    }
    public static function getRuleLB()
    {
        return [
            'name' => 'load-balancing',
            'conditions' => [
                "env.mode == 'lb'",
            ],
            'flow' => 'typeB',
        ];
    }
    public static function getRuleLOC()
    {
        return [
            'name' => 'change-location',
            'conditions' => [
                "env.mode == 'cl'",
            ],
            'flow' => [
                'name' => 'typeB',
                'locations' => [
                    'mod1' => Config::$node3,
                    'mod2' => Config::$node2,
                    'mod3' => Config::$node1,
                ],
            ],
        ];
    }
    public static function getRuleLOCA()
    {
        return [
            'name' => 'change-location',
            'conditions' => [
                "env.mode == 'cl'",
            ],
            'flow' => [
                'name' => 'typeA',
                'locations' => [
                    'mod1' => Config::$node3,
                    'mod2' => Config::$node2,
                    'mod3' => Config::$node1,
                ],
            ],
        ];
    }
    public static function getRuleCurrent()
    {
        return [
            'name' => 'current',
            'conditions' => [
                "env.mode == 'current'",
            ],
            'flow' => [
                'name' => 'typeA',
            ],
        ];
    }

    // PHPUnit Framework

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
    }
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
    }

    public function setUp()
    {
        parent::setUp();

        // ModuleTest::registerModules();
    }
    public function tearDown()
    {
        parent::tearDown();
    }

    // Tests

    public function testDummy()
    {
        $this->assertTrue(true);
    }
}

