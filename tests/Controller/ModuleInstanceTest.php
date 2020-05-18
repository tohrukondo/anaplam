<?php

namespace Anaplam\Tests;

require_once 'Config.php';
require_once 'BaseTestCase.php';
require_once 'FlowTest.php';
require_once 'RulesFlowTest.php';
require_once 'ModuleTest.php';

class ModuleInstanceTest extends BaseTestCase
{
    static $function = 'flows';
    static $flow;
    static $rules_flow;

    // module instance.

    public static function getMod1()
    {
        return [
            'name' => 'mod1',
            'module' => 'access',
            'location' => Config::$node1,
            'debug' => "on",
            'config' => [
                'destination' => [
                    'mod2',
                ],
                'params' => [
                    'show' => '{mod2}',
                    'param1' => '1111',
                    'param2' => '222',
                    'param3' => '33',
                ],
            ],
        ];
    }
    public static function getMod1b()
    {
        return [
            'name' => 'mod1',
            'module' => 'access',
            'location' => Config::$node2,
            'debug' => "on",
            'volumes' => [
                ['name' => 'data', 'hostPath' => ['path' => '/data/access', 'type' => 'DirectoryOrCreate']],
                ['name' => 'error-log', 'hostPath' => ['path' => '/data/access/error_log', 'type' => 'FileOrCreate']],
            ],
            'config' => [
                'source' => [
                    'mod2',
                ],
                'destination' => [
                    'mod3',
                ],
                'params' => [
                    'show' => '{mod3}',
                    'param1' => '4444',
                    'param2' => '55555',
                    'param3' => '666666',
                ],
            ],
        ];
    }
    public static function getMod2()
    {
        return [
            'name' => 'mod2',
            'module' => 'ifthen-dummy',
            'location' => Config::$node2,
            'debug' => "off",
            'volumes' => [
                ['name' => 'data', 'hostPath' => ['path' => '/data/ifthen', 'type' => 'DirectoryOrCreate']],
                ['name' => 'error-log', 'hostPath' => ['path' => '/data/ifthen/error_log', 'type' => 'FileOrCreate']],
            ],
            'config' => [
                'source' => [
                    'mod1',
                ],
                'destination' => [
                    'mod3',
                ],
                'params' => [
                    'if' => [
                        'data["contents"]["temperature"] > 20.0',
                        'curl -k http://127.0.0.1',
                        'mv /dev/null',
                    ],
                    'if' => [
                        '${name} == 1000',
                        'curl -X POST -d POST {mod1}',
                        'curl -X POST -d POST {mod3}',
                    ],
                ],
            ],
        ];
    }
    public static function getMod2b()
    {
        return [
            'name' => 'mod2',
            'module' => 'ifthen-dummy',
            'location' => Config::$node1,
            'debug' => "off",
            'volumes' => [
                ['name' => 'data', 'hostPath' => ['path' => '/data/ifthen']],
                ['name' => 'error-log', 'hostPath' => ['path' => '/data/ifthen/error_log']],
            ],
            'config' => [
                'destination' => [
                    'mod1',
                ],
                'params' => [
                    'if' => [
                        'data["contents"]["temperature"] > 10.0',
                        'curl -k http://127.0.0.1',
                        'mv /dev/null',
                    ],
                    'if' => [
                        '${name} == 2000',
                        'curl -X POST -d POST {mod1}',
                        'curl -X POST -d POST {mod3}',
                    ],
                ],
            ],
        ];
    }
    public static function getMod3()
    {
        return [
            'name' => 'mod3',
            'module' => 'show',
            'debug' => "off",
            'volumes' => [
                ['name' => 'data', 'hostPath' => ['path' => '/data/show', 'type' => 'DirectoryOrCreate']],
                ['name' => 'error-log', 'hostPath' => ['path' => '/data/show/error_log', 'type' => 'FileOrCreate']],
            ],
            'config' => [
                'source' => [
                    'mod2',
                ],
                'params' => [
                    'p4' => '444444',
                    'p5' => '555555',
                    'p6' => '666666',
                    'mod1url' => '{mod1}',
                    'mod2url' => '{mod2}',
                    'mod3url' => '{mod3}',
                ],
            ],
        ];
    }
    public static function getMod3b()
    {
        return [
            'name' => 'mod3',
            'module' => 'show',
            'location' => Config::$node1,
            'debug' => "off",
            'volumes' => [
                ['name' => 'data', 'hostPath' => ['path' => '/data/show']],
                ['name' => 'error-log', 'hostPath' => ['path' => '/data/show/error_log']],
            ],
            'config' => [
                'source' => [
                    'mod1',
                ],
                'params' => [
                    'p4' => '444444',
                    'p5' => '555555',
                    'p6' => '666666',
                    'mod1url' => '{mod1}',
                    'mod2url' => '{mod2}',
                    'mod3url' => '{mod3}',
                ],
            ],
        ];
    }

    // PHPUnit Framework

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        static::$flow = new FlowTest();
        static::$flow->setUpBeforeClass();
        static::$rules_flow = new RulesFlowTest();
        static::$rules_flow->setUpBeforeClass();
    }
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::$flow->tearDownAfterClass();
        static::$rules_flow->tearDownAfterClass();
    }

    public function setUp()
    {
        parent::setUp();
        static::$flow->setUp();
        static::$rules_flow->setUp();
    }
    public function tearDown()
    {
        parent::tearDown();
        static::$flow->tearDown();
        static::$rules_flow->tearDown();
    }

    // Utilities.

    // Assertions.

    // Tests.

    public function testGetModuleInstanceOfFlow()
    {
        static::$flow->createFlowCallback(function($result){
            $flowName = $result['metadata']['name'];
            foreach ($result['flow'] as $moduleInstance){
                $name = $moduleInstance['name'];
                $r = static::$flow->get([$flowName, $name]);
                $this->assertSame($r['name'] ?? null, $name);
                foreach (['url', 'instance_name', 'module', 'uuid', 'creationTimestamp', 'lastModifiedTimestamp'] as $entry){
                    $this->assertArrayHasKey($entry, $r);
                }
            }
        });
    }
    public function testGetModuleInstanceOfFlowNoexistent()
    {
        static::$flow->createFlowCallback(function($result){
            $flowName = $result['metadata']['name'];
            $r = static::$flow->get([$flowName, 'naiyo']);
            $this->assertArrayHasKey('error', $r, json_encode($r));
            $this->assertContains("module instance 'naiyo' not found.", $r['error']);
        });
    }
    public function testGetModuleInstanceOfRulesFlow()
    {
        static::$rules_flow->createRuleFlowCallback(function($result){
            #$this->assertFalse(json_encode($result));
            $rulesFlowName = $result['metadata']['name'];
            foreach ($result['flows'] as $flow){
                $flowName = $flow['name'];
                foreach ($flow['flow'] as $moduleInstance){
                    $name = $moduleInstance['name'];
                    $r = static::$rules_flow->get([$rulesFlowName, $flowName, $name]);
                    $this->assertSame($r['name'] ?? null, $name);
                    foreach (['type', 'instance_name', 'module', 'uuid', 'creationTimestamp', 'lastModifiedTimestamp'] as $entry){
                        $this->assertArrayHasKey($entry, $r);
                    }
                }
            }
        });
    }
    public function testGetModuleInstanceOfRulesFlowNoexistent()
    {
        static::$rules_flow->createRuleFlowCallback(function($result){
            #$this->assertFalse(json_encode($result));
            $rulesFlowName = $result['metadata']['name'];
            foreach ($result['flows'] as $flow){
                $flowName = $flow['name'];
                $r = static::$rules_flow->get([$rulesFlowName, $flowName, 'naiyo']);
                $this->assertArrayHasKey('error', $r, json_encode($r));
                $this->assertContains("module instance 'naiyo' not found.", $r['error']);
            }
        });
    }
}

