<?php

namespace Anaplam\Tests;

use Anaplam\Controller\RulesFlowController;

require_once 'ModuleTest.php';
require_once 'ModuleInstanceTest.php';
require_once 'RuleTest.php';
require_once 'EnvTest.php';

class RulesFlowTest extends BaseTestCase
{
    protected static $function = 'flows';

    protected static $rule;
    protected static $module;

    // flow.
    public static function getTypeA()
    {
        return [
            'name' => 'typeA',
            'description' => 'typeA description.',
            'flow' => [
                ModuleInstanceTest::getMod1(),
                ModuleInstanceTest::getMod2(),
                ModuleInstanceTest::getMod3(),
            ],
        ];
    }
    public static function getTypeB()
    {
        return [
            'name' => 'typeB',
            'description' => 'typeB description.',
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

        static::$rule = new RuleTest();
        static::$module = new ModuleTest();
    }
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
    }

    public function setUp()
    {
        parent::setUp();

        static::$module->registerModules();
    }
    public function tearDown()
    {
        parent::tearDown();

        $pods = trim($this->getKubeNames('po')); 
        $this->assertEmpty($pods, $pods); 
    }

    // Utilities.

    public function createRuleFlowCallback($callback)
    {
        $params = $this->getParam1();
        $result = $this->post(null, $params);
        if ($callback){
            $callback($result);
        }
        $response = $this->delete($params['metadata']['name']);
    }
    protected function getParam1()
    {
        return [
            'metadata' => [
                'name' => 'hoge',
                'description' => 'hoge desc',
                'nodePort' => 30002,
            ],
            'rules' => [ RuleTest::getRuleDefaultTypeA() ],
            'flows' => [ static::getTypeA() ],
        ];
    }
    protected function getParam2()
    {
        return [
            'metadata' => [
                'name' => 'hoge',
                'description' => 'hoge desc',
                'nodePort' => 30004,
            ],
            'rules' => [ RuleTest::getRuleDefault(), RuleTest::getRuleLL(), RuleTest::getRuleLB() ],
            'flows' => [ static::getTypeA(), static::getTypeB(), ],
            'env'  => EnvTest::$env1,
        ];
    }
    protected function getParam3()
    {
        return [
            'metadata' => [
                'name' => 'hoge',
                'description' => 'hoge desc',
                'nodePort' => 30005,
            ],
            'rules' => [ RuleTest::getRuleDefault(), RuleTest::getRuleLL(), RuleTest::getRuleLB(), RuleTest::getRuleLOC(), ],
            'flows' => [ static::getTypeA(), static::getTypeB(), ],
            'env'  => EnvTest::$env1,
        ];
    }
    protected function getParam4()
    {
        return [
            'metadata' => [
                'name' => 'hoge',
                'description' => 'hoge desc',
                'nodePort' => 30005,
            ],
            'rules' => [ RuleTest::getRuleDefault(), RuleTest::getRuleLL(), RuleTest::getRuleLB(), RuleTest::getRuleLOCA(), ],
            'flows' => [ static::getTypeA(), static::getTypeB(), ],
            'env'  => EnvTest::$env1,
        ];
    }
    protected function getCurrentFlow($params, $current, $rule = true)
    {
        $flow = $current;
        while ($rule && $current){
            $rules = array_filter($params['rules'], function($v) use($current){ return strcmp($v['name'], $current) == 0; });
            $this->assertCount(1, $rules);
            reset($rules);
            $flow = value($rules)['flow'] ?? null;
            if (is_null($flow) && isset(value($rules)['rule'])){
                $current = value($rules)['rule'] ?? null;
            } else {
                break;
            }
        }
        return $flow;
    }

    // Assertions.

    protected function assertRuleFlow($params, $result, $current, $rule = true)
    {
        $this->assertCurrentRule($params, $result, $current, $rule);
        $this->assertAppropriatePodsExist($params, $current, $rule);
        $this->assertAppropriateEndpoint($params);
    }
    protected function assertCurrentRule($params, $result, $current, $rule = true)
    {
        // current rule check.
        $cur_rule_index = count($params['rules'] ?? []);
        $this->assertSame('current', $result['rules'][$cur_rule_index]['name'] ?? null, json_encode($result));
        if ($rule){
            $this->assertSame($current, $result['rules'][$cur_rule_index]['rule']);
            $this->assertArrayNotHasKey('flow', $result['rules'][$cur_rule_index]);
        } else {
            $this->assertArrayNotHasKey('rule', $result['rules'][$cur_rule_index]);
            $this->assertSame($current, $result['rules'][$cur_rule_index]['flow']);
        }
    }
    protected function assertAppropriatePodsExist($params, $current, $rule = true)
    {
        $flowName = $this->getCurrentFlow($params, $current, $rule);
        foreach ($params['flows'] as $flows){
            if (strcmp($flows['name'], $flowName) == 0){
                foreach ($flows['flow'] as $flow){
                    // Pods selected by rules_flow and flow labels exist.
                    $podNames = $this->getPods($flowName, $flow['name'], $flow['module']);
                    $this->assertCount(1, $podNames, json_encode($podNames));
                    $this->assertNotEmpty($podNames[0]);
                }
                break;
            }
        }
    }
    protected function assertAppropriateEndpoint($params)
    {
        // rule flow's endpoint check.
        $endpoints = $this->getEndpointService();
        $this->assertCount(1, $endpoints, "endpoints = " . json_encode($endpoints));
        $this->assertSame($params['metadata']['name'], $endpoints[0] ?? "endpoints is null");
    }

    // Tests.

    public function testCreateRuleFlowNoName()
    {
        $params = $this->getParam1();
        unset($params['metadata']['name']);
        $result = $this->post(null, $params);

        $this->assertContains("rule flow requires 'name'", $result['error']);
    }
    public function testCreateRuleFlowNameLengthExceed()
    {
        $params = $this->getParam1();
        $params['metadata']['name'] = 'a123456780123456780123456780123456780123456789999901234567890123';
        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
    }
    public function testCreateRuleFlowNameMaxLength()
    {
        $params = $this->getParam1();
        $params['metadata']['name'] = 'a12345678012345678012345678012345678012345678999990123456789012';
        $result = $this->post(null, $params);

        $this->assertArrayNotHasKey('error', $result, json_encode($result));
        $response = $this->delete($params['metadata']['name']);
    }
    public function testCreateRuleFlowNameAndModuleInstanceAndModuleNameMaxLength()
    {
        $module = static::$module->registerModuleWithMaxLengthName();
        $params = $this->getParam1();
        $params['metadata']['name'] = 'a12345678012345678012345678012345678012345678999990123456789012';
        $params['flows'][0]['flow'][0]['name'] = $params['metadata']['name'];
        $params['flows'][0]['flow'][0]['module'] = $module['metadata']['name'];
        $params['flows'][0]['flow'][1]['config']['source'][0] = $params['metadata']['name'];
        $result = $this->post(null, $params);

        $this->assertArrayNotHasKey('error', $result, json_encode($result));
        static::$module->deleteModule($module['metadata']['name']);
        $response = $this->delete($params['metadata']['name']);
    }
    public function testCreateRuleFlowNoNodeport()
    {
        $params = $this->getParam1();
        unset($params['metadata']['nodePort']);
        $result = $this->post(null, $params);

        $this->assertContains("rule flow requires 'nodePort'", (string)$result['error']);
    }
    public function testCreateRuleFlowModuleIsNotExists()
    {
        $no_module = 'no-module';
        $params = $this->getParam1();
        $params['flows'][0]['flow'][0]['module'] = $no_module;
        $result = $this->post(null, $params);

        $this->assertContains("module '". $no_module . "' not found.", $result['error']);
    }
    public function testCreateRuleFlowWithModuleHasNonexistentImage()
    {
        $moduleName = 'test';
        $result = static::$module->registerModuleWithNonexistentImage($moduleName);
        $this->assertArrayNotHasKey('error', $result);

        $params = $this->getParam1();
        $params['flows'][0]['flow'][0]['module'] = $moduleName;
        $result = $this->post(null, $params);

        $this->assertContains("pod initialize failed. status is 'Pending'.", $result['error'] ?? "", json_encode($result));
    }
    public function testCreateRuleFlowWithNonexistentNode()
    {
        $no_location = 'no-location';
        $params = $this->getParam1();
        $params['flows'][0]['flow'][0]['location'] = $no_location;
        $result = $this->post(null, $params);

        $this->assertContains("location '". $no_location . "' not found.", $result['error']);
    }
    public function testCreateRuleFlowWithDuplicatedModuleInstanceName()
    {
        $params = $this->getParam1();
        $module_name = $params['flows'][0]['flow'][1]['name'];
        $params['flows'][0]['flow'][0]['name'] = $module_name;
        $result = $this->post(null, $params);

        $this->assertContains("flow 'name' is duplicated. (`" . $module_name . "`)", $result['error']);
    }
    public function testCreateOneFlowWithDuplicatedRuleName()
    {
        $params = $this->getParam1();
        $params['rules'][] = RuleTest::getRuleLL();
        $params['rules'][] = RuleTest::getRuleLL();
        $result = $this->post(null, $params);

        $this->assertContains("rule 'name' is duplicated. (`low-latency`)", $result['error']);
    }
    public function testCreateOneFlowWithCurrentRuleName()
    {
        $params = $this->getParam1();
        $params['rules'][] = RuleTest::getRuleCurrent();
        $result = $this->post(null, $params);

        $this->assertContains("'current' is read only property.", $result['error']);
    }
    public function testCreateOneFlowWithNoRule()
    {
        $params = $this->getParam1();
        unset($params['rules']);
        $result = $this->post(null, $params);

        $this->assertRuleFlow($params, $result, 'typeA', false);

        $response = $this->delete($params['metadata']['name']);
    }
    public function testCreateRuleFlowAndOneMoreWithSameName()
    {
        $params = $this->getParam1();
        $result = $this->post(null, $params);

        $this->assertRuleFlow($params, $result, 'default');

        $result = $this->post(null, $params);
        $this->assertContains("rule flow 'name' already exists. (`hoge`)", $result['error']);

        $response = $this->delete($params['metadata']['name']);
    }
    public function testCreateRuleFlowAndOneMoreWithExistentNodePort()
    {
        $params = $this->getParam1();
        $result = $this->post(null, $params);

        $this->assertRuleFlow($params, $result, 'default');

        $params['metadata']['name'] = 'hoge2';
        $result = $this->post(null, $params);

        $this->assertContains("rule flow 'nodePort' already exists. (`" .
            $params['metadata']['nodePort'] . "`)", $result['error']);

        $response = $this->delete('hoge');
    }
    public function testCreateTwoFlow()
    {
        $params = $this->getParam1();
        $result = $this->post(null, $params);

        $this->assertSame('current', $result['rules'][1]['name'] ?? json_encode($result));
        $this->assertSame('default', $result['rules'][1]['rule'] ?? json_encode($result));
        $endpoints = $this->getEndpointService();
        $this->assertCount(1, $endpoints, json_encode($endpoints));
        $this->assertSame('hoge', $endpoints[0]);

        $params['metadata']['name'] = 'hoge2';
        $params['metadata']['nodePort'] = 30003;
        $result = $this->post(null, $params);

        # 6 pods exist.
        $podinfo = trim($this->getKubeNames('po'));
        $names = explode("\n", $podinfo);
        $this->assertCount(6, $names, $podinfo);

        # 2 endpoint services exist.
        $endpoints = $this->getEndpointService();
        $this->assertCount(2, $endpoints);
        foreach ($endpoints as $endpoint){
            $this->assertContains('hoge', $endpoint);
        }
        # rules
        $result = static::$rule->get($params['metadata']['name']);
        foreach ($result['rules'] as $r){
            switch ($r['name']){
            case 'default':
                $this->assertSame('typeA', $r['flow']);
                break;
            case 'current':
                $this->assertSame('default', $r['rule']);
                $this->assertArrayNotHasKey('flow', $r);
                break;
            }
        }
        #$this->assertSame('error!!!', shell_exec('kubectl get po,svc,ep -o wide'));

        # 30002, 30003 request.
        $body = [];
        foreach (['30002', '30003'] as $port){
            #$body[$port] = json_encode(yaml_parse((string)static::getGuzzleClient()->get('http://127.0.0.1:'. $port)->getBody()));
            $body[$port] = shell_exec('curl -k 127.0.0.1:' . $port . ' 2> /dev/null');
            $this->assertContains("curl -X POST -d POST 10.", $body[$port]);
            $this->assertNotContains('{mod1}', $body[$port]);
            $this->assertNotContains('{mod2}', $body[$port]);
            $this->assertNotContains('{mod3}', $body[$port]);
            foreach (Config::getNodes() as $host){
                #$r = json_encode(yaml_parse((string)static::getGuzzleClient()->get('http://' . $host . ':' . $port)->getBody()));
                $r = shell_exec('curl -k ' . $host . ':' . $port . ' 2> /dev/null');
                $this->assertSame($body[$port], $r, $body[$port] . ' is not ' . $r);
            }
        }
        $this->assertNotSame($body['30002'], $body['30003'], "30002 => " . $body['30002'] . ", 30003 => " . $body['30003']);

        # delete
        $this->delete('hoge2');
        $this->delete('hoge');
    }
    public function testCreateRuleFlow1()
    {
        $params = $this->getParam2();
        $flowName = $params['metadata']['name'];

        $result = $this->post(null, $params);
        $this->assertRuleFlow($params, $result, 'low-latency');

        $r_result = static::$rule->post($flowName, ['env' => EnvTest::$env2]);
        $result['rules'] = $r_result['rules'];
        $result['env'] = EnvTest::$env2;
        $this->assertRuleFlow($params, $result, 'load-balancing');

        $r_result = static::$rule->post($flowName, ['env' => EnvTest::$env3]);
        $result['rules'] = $r_result['rules'];
        $result['env'] = EnvTest::$env3;
        $this->assertRuleFlow($params, $result, 'default');

        $this->delete($flowName);
    }
    public function testCreateRuleFlow2()
    {
        $params = $this->getParam2();
        $flowName = $params['metadata']['name'];
        $params['env'] = EnvTest::$env2;
        $result = $this->post(null, $params);

        $this->assertRuleFlow($params, $result, 'load-balancing');

        $r_result = static::$rule->post($flowName, ['env' => EnvTest::$env_no_mode]);
        $result['rules'] = $r_result['rules'];
        $result['env'] = EnvTest::$env2;
        $this->assertRuleFlow($params, $result, 'default');

        $r_result = static::$rule->post($flowName, ['env' => []]);
        if (!array_key_exists('rules', $r_result)){
            $this->assertFalse(json_encode($r_result));
        }
        $result['rules'] = $r_result['rules'];
        $result['env'] = EnvTest::$env2;
        $this->assertRuleFlow($params, $result, 'default');

        $this->delete($flowName);
    }
    public function testCreateRuleFlow3()
    {
        $params = $this->getParam2();
        $flowName = $params['metadata']['name'];
        $params['env'] = EnvTest::$env3;
        $result = $this->post(null, $params);

        $this->assertRuleFlow($params, $result, 'default');

        $r_result = static::$rule->post($flowName, ['env' => EnvTest::$env_rtt_ill]);
        $this->assertArrayHasKey('error', $r_result);

        $this->delete($flowName);
    }
    public function testCreateRuleFlowChangeLocation()
    {
        $params = $this->getParam4();
        $flowName = $params['metadata']['name'];
        $result = $this->post(null, $params);
        $access = $this->getPods(null, null, 'access', true);
        $ifthen = $this->getPods(null, null, 'ifthen-dummy', true);
        $logs = shell_exec("kubectl get po,svc,ep -o wide --show-labels");

        $this->assertRuleFlow($params, $result, 'low-latency');

        $r_result = static::$rule->post($flowName, ['env' => EnvTest::$env_cl]);
        $access2 = $this->getPods(null, null, 'access', true);
        $ifthen2 = $this->getPods(null, null, 'ifthen-dummy', true);
        $logs2 = shell_exec("kubectl get po,svc,ep -o wide --show-labels");
        $this->assertRuleFlow($params, $r_result, 'change-location');

        $this->assertNotSame($access, $access2, json_encode([$logs, $logs2]));
        $this->assertSame($ifthen, $ifthen2, json_encode([$logs, $logs2]));

        $this->delete($flowName);
    }
    public function testCreateRuleNotAllowedBySource()
    {
        $params = $this->getParam2();
        $params['flows'][0]['flow'][1]['config']['source'] = ['mod3'];
        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("'mod1' is not allowed as a source of 'mod2' by the module instance's `source`.", $result['error']);
    }
    public function testCreateRuleDenySource()
    {
        $params = $this->getParam2();

        $flowA = &$params['flows'][0]['flow'];
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
    public function testCreateRuleDenyDestination()
    {
        $params = $this->getParam2();

        // show(mod3) deny destination do not allow access(mod1)
        $flowA = &$params['flows'][0]['flow'];
        $flowA[0]['config']['source'] = ['mod3'];
        unset($flowA[0]['config']['destination']);
        unset($flowA[1]['config']['source']);
        $flowA[1]['config']['destination'] = ['mod3'];
        $flowA[2]['config']['destination'] = ['mod1'];

        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("'mod3' is not allowed as a source of 'mod1' by the module's `denyDestination`.", $result['error']);
    }
    public function testCreateRuleFlowIllegalCondition()
    {
        $params = $this->getParam2();
        $params['rules'][1]['conditions'][0] = "'illegal' = env.conditions";
        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("condition parse error '='", $result['error']);
    }
    public function testCreateRuleFlowCouldnotDetermineFlow()
    {
        $params = $this->getParam2();
        $params['rules'] = [ RuleTest::getRuleLL(), RuleTest::getRuleLB() ]; // no default.
        $params['env'] = EnvTest::$env3;
        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("the flow could not be determined.", $result['error']);
    }
    public function testCreateRuleFlowNoRuleFlow()
    {
        $params = $this->getParam2();
        $params['rules'][1]['flow'] = "no flow";
        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("flow 'no flow' not found.", $result['error']);
    }
    public function testCreateRuleFlowNoDefaultRuleFlow()
    {
        $params = $this->getParam2();
        unset($params['rules'][0]['rule']);
        $params['rules'][0]['flow'] = "no flow";
        $result = $this->post(null, $params);

        $this->assertArrayHasKey('error', $result, json_encode($result));
        $this->assertContains("flow 'no flow' not found.", $result['error']);
    }
    public function testGetFlow()
    {
        $this->createRuleFlowCallback(function ($result){
            $this->assertArrayHasKey('metadata', $result, json_encode($result));
            $metadata = $result['metadata'];
            $this->assertArrayHasKey('name', $metadata, json_encode($metadata));
            $rulesFlowName = $metadata['name'];
            foreach ($result['flows'] as $flow){
                $this->assertArrayHasKey('name', $flow, json_encode($flow));
                $fdata = $this->get([$rulesFlowName, $flow['name']]);
                $this->assertSame(json_encode($flow), json_encode($fdata['flows'][0] ?? null), json_encode($fdata));
            }
        });
    }
    public function testGetRuleFlowNoexistentRulesFlow()
    {
        $this->createRuleFlowCallback(function ($result){
            $r = $this->get('hoehoe');
            $this->assertArrayHasKey('error', $r, json_encode($r));
            $this->assertContains("flow 'hoehoe' not found.", $r['error']);
        });
    }
    public function testGetRuleFlowByUuid()
    {
        $this->createRuleFlowCallback(function ($result){
            $this->assertArrayHasKey('metadata', $result, json_encode($result));
            $this->assertArrayHasKey('rules', $result, json_encode($result));
            $this->assertArrayHasKey('flows', $result, json_encode($result));
            $this->assertArrayHasKey('env', $result, json_encode($result));
            $metadata = $result['metadata'];
            $this->assertArrayHasKey('name', $metadata, json_encode($metadata));
            $this->assertArrayHasKey('uuid', $metadata, json_encode($metadata));
            $r = $this->get($metadata['uuid']);
            $this->assertArrayHasKey('metadata', $r, json_encode($r));
            $this->assertArrayHasKey('name', $r['metadata'], json_encode($r['metadata']));
            $this->assertSame($metadata['name'], $r['metadata']['name'], json_encode($result));
        });
    }
    public function testGetRuleFlowNoexistentFlow()
    {
        $this->createRuleFlowCallback(function ($result){
            $result = $this->get([$result['metadata']['name'], 'hoehoe']);
            $this->assertArrayHasKey('error', $result, json_encode($result));
            $this->assertContains("flow 'hoehoe' not found.", $result['error']);
        });
    }
    public function testGetFlowOnRuleFlowByUuid()
    {
        $this->createRuleFlowCallback(function ($result){
            $this->assertArrayHasKey('metadata', $result, json_encode($result));
            $this->assertArrayHasKey('rules', $result, json_encode($result));
            $this->assertArrayHasKey('flows', $result, json_encode($result));
            $this->assertArrayHasKey('env', $result, json_encode($result));
            $this->assertArrayHasKey('name', $result['metadata'], json_encode($result['metadata']));
            $name = $result['metadata']['name'];
            $flows = $result['flows'];
            $this->assertNotEmpty($flows);
            $this->assertArrayHasKey('uuid', $flows[0], json_encode($flows[0]));
            $flowUuid = $flows[0]['uuid'];
            $this->assertArrayHasKey('name', $flows[0], json_encode($flows[0]));
            $flowName = $flows[0]['name'];

            $r = $this->get([$name, $flowUuid]);
            $this->assertArrayHasKey('flows', $r, json_encode($r));
            $fs = $r['flows'];
            $this->assertSame(count($flows), count($fs));
            $this->assertSame($flows[0]['name'], $fs[0]['name']);
        });
    }
}

