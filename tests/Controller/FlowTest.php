<?php

namespace Anaplam\Tests;

use Anaplam\Controller\FlowController;

require_once 'ModuleTest.php';
require_once 'ModuleInstanceTest.php';

class FlowTest extends BaseTestCase
{
    protected static $function = 'flows';
    protected static $module;

    // flow.
    public static function getFlowA()
    {
        return [
            'metadata' => [
                'name' => 'hoge',
                'description' => 'hoge description.',
            ],
            'flow' => [
                ModuleInstanceTest::getMod1(),
                ModuleInstanceTest::getMod2(),
                ModuleInstanceTest::getMod3(),
            ],
        ];
    }
    public static function getFlowB()
    {
        return [
            'metadata' => [
                'name' => 'typeB',
                'description' => 'typeB description.',
            ],
            'flow' => [
                ModuleInstanceTest::getMod1b(),
                ModuleInstanceTest::getMod2b(),
                ModuleInstanceTest::getMod3b(),
            ],
        ];
    }

    // PHPUnit Framework

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        static::$module = new ModuleTest();
        static::$module->setUpBeforeClass();
    }
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
    }

    public function setUp()
    {
        parent::setUp();

        parent::setVersion('v1');

        static::$module->registerModules();
    }
    public function tearDown()
    {
        parent::tearDown();

        $pods = trim($this->getKubeNames('po'));
        $this->assertEmpty($pods, $pods);
    }

    // Utility

    public function createFlowCallback($callback)
    {
        $params = $this->getFlowA();
        $result = $this->post(null, $params);
        if ($callback){
            $callback($result);
        }
        $response = $this->delete($params['metadata']['name']);
    }

    // Assertions.

    protected function assertFlow($params)
    {
        $this->assertAppropriatePodsExist($params);
    }
    protected function assertAppropriatePodsExist($params)
    {
        foreach ($params['flow'] as $flow){
            $podNames = $this->getPods(null, $flow['name'], $flow['module']);
            $this->assertCount(1, $podNames, json_encode($podNames));
            $this->assertNotEmpty($podNames[0]);
        }
    }

    // Tests.

    public function testCreateFlowNoName()
    {
        $params = $this->getFlowA();
        unset($params['metadata']['name']);
        $result = $this->post(null, $params);

        $this->assertContains("flow requires 'name'", $result['error']);
    }
    public function testCreateFlowNameLengthExceed()
    {
        $params = $this->getFlowA();
        $params['metadata']['name'] = '0123456780123456780123456780123456780123456789999901234567890123';
        $result = $this->post(null, $params);
        $this->assertContains("flow 'name' must not exceed maximum length 63", $result['error']);
    }
    public function testCreateFlowNameMaxLength()
    {
        $params = $this->getFlowA();
        $params['metadata']['name'] = '012345678012345678012345678012345678012345678999990123456789012';
        $result = $this->post(null, $params);

        $this->assertArrayNotHasKey('error', $result, json_encode($result));
        $response = $this->delete($params['metadata']['name']);
    }
    public function testCreateFlowModuleIsNotExists()
    {
        $no_module = 'no-module';
        $params = $this->getFlowA();
        $params['flow'][0]['module'] = $no_module;
        $result = $this->post(null, $params);

        $this->assertContains("module '". $no_module . "' not found.", $result['error']);
    }
    public function testCreateFlowWithModuleHasNonexistentImage()
    {
        $moduleName = 'test';
        $result = static::$module->registerModuleWithNonexistentImage($moduleName);
        $this->assertArrayNotHasKey('error', $result);

        $params = $this->getFlowA();
        $params['flow'][0]['module'] = $moduleName;
        $result = $this->post(null, $params);

        $this->assertContains("pod initialize failed. status is 'Pending'.", $result['error'] ?? "", json_encode($result));
        $this->delete($params['metadata']['name']);
    }
    public function testCreateFlowWithNonexistentNode()
    {
        $no_location = 'no-location';
        $params = $this->getFlowA();
        $params['flow'][0]['location'] = $no_location;
        $result = $this->post(null, $params);

        $this->assertContains("location '". $no_location . "' not found.", $result['error']);
    }
    public function testCreateFlowWithDuplicatedModuleInstanceName()
    {
        $params = $this->getFlowA();
        $module_name = $params['flow'][1]['name'];
        $params['flow'][0]['name'] = $module_name;
        $result = $this->post(null, $params);

        $this->assertContains("flow 'name' is duplicated. (`" . $module_name . "`)", $result['error']);
    }
    public function testCreateFlowAndOneMoreWithSameName()
    {
        $params = $this->getFlowA();
        $result = $this->post(null, $params);
        $this->assertFlow($params);

        foreach ($result['flow'] as $flow){
            $body[$flow['name']] = json_encode(shell_exec('curl -k ' . $flow['url'] . ' 2> /dev/null'));
            #$body[$flow['name']] = json_encode(yaml_parse((string)static::getGuzzleClient()->get($flow['url'])->getBody()));
            $this->assertNotContains(json_encode($body[$flow['name']]), 'mod1');
            $this->assertNotContains(json_encode($body[$flow['name']]), 'mod2');
            $this->assertNotContains(json_encode($body[$flow['name']]), 'mod3');
        }
        $result = $this->post(null, $params);
        $this->assertContains("flow 'name' already exists. (`hoge`)", $result['error'], $result['error']);

        $this->delete($params['metadata']['name']);
    }
    public function testCreateTwoFlow()
    {
        $params = $this->getFlowA();
        $params['metadata']['name'] = 'hoge';
        $result = $this->post(null, $params);

        $params['metadata']['name'] = 'hoge2';
        $result = $this->post(null, $params);

        # 6 pods exist.
        $podinfo = trim($this->getKubeNames('po'));
        $names = explode("\n", $podinfo);
        $this->assertCount(6, $names, $podinfo);

        # delete
        $this->delete('hoge2');
        $this->delete('hoge');
    }
    public function testCreateFlowNotAllowedBySource()
    {
        $params = $this->getFlowA();
        $params['flow'][1]['config']['source'] = ['mod3'];
        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("'mod1' is not allowed as a source of 'mod2' by the module instance's `source`.", $result['error']);
    }
    public function testCreateFlowDenySource()
    {
        $params = $this->getFlowA();

        $flowA = &$params['flow'];
        $flowA[0]['config']['source'] = ['mod2'];
        unset($flowA[0]['config']['destination']);
        $flowA[1]['config']['source'] = ['mod3'];
        $flowA[1]['config']['destination'] = ['mod1'];
        unset($flowA[2]['config']['source']);
        $flowA[2]['config']['destination'] = ['mod2'];

        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("'mod3' is not allowed as a source of 'mod2' by the module's `denySource`.", $result['error']);
    }
    public function testCreateFlowDenyDestination()
    {
        $params = $this->getFlowA();

        // show(mod3) deny destination do not allow access(mod1)
        $flowA = &$params['flow'];
        $flowA[0]['config']['source'] = ['mod3'];
        unset($flowA[0]['config']['destination']);
        unset($flowA[1]['config']['source']);
        $flowA[1]['config']['destination'] = ['mod3'];
        $flowA[2]['config']['destination'] = ['mod1'];

        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("'mod3' is not allowed as a source of 'mod1' by the module's `denyDestination`.", $result['error']);
    }
    public function testUpdateFlow()
    {
        $params = $this->getFlowA();
        $params['metadata']['name'] = 'hoge';
        $result = $this->post(null, $params);
        $flowA = &$params['flow'];
        $flowA[0]['config']['params']['param1'] = 4444;
        $flowA[0]['config']['params']['param2'] = 'p2';
        $result = $this->post($params['metadata']['name'], $params);
        $this->assertEquals($flowA[0]['config']['params'], $result['flow'][0]['config']['params']);

        # delete
        $this->delete('hoge');
    }
}

