<?php

namespace Anaplam\Tests;

use Anaplam\Controller\ModuleController;

require_once 'Config.php';
require_once 'BaseTestCase.php';

class ModuleTest extends BaseTestCase
{
    protected static $function = 'modules';

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
    }
    public function tearDown()
    {
        parent::tearDown();
    }

    // module
    public static function getAccess()
    {
        return [
            'metadata' => [
                'name' => 'access',
                'description' => 'access to show module test on sakura cloud',
                'labels' => [
                    'app' => 'access',
                    'run' => 'show-test',
                ],
            ],
            'modules' => [
                'image' => Config::$master . ':5000/access',
                'containerPort' => 80,
                'denySource' => ['module2', 'module4',],
                'denyDestination' => [],
                'volumeMounts' => [
                    ['name' => 'data', 'mountPath' => '/data'],
                ],
            ],
        ];
    }
    public static function getIfthenDummy()
    {
        return [
            'metadata' => [
                'name' => 'ifthen-dummy',
                'description' => 'ifthen dummy module',
                'labels' => [
                    'app' => 'app33',
                    'run' => 'ifthen-test',
                ],
            ],
            'modules' => [
                'image' => Config::$master . ':5000/ifthen-dummy',
                'containerPort' => 80,
                'denySource' => ['show', 'comp', 'datastore',],
                'denyDestination' => ['module2', 'module4'],
                'volumeMounts' => [
                    ['name' => 'data', 'mountPath' => '/data'],
                    ['name' => 'error-log', 'mountPath' => '/var/log/httpd/error_log'],
                ],
            ],
        ];
    }
    public static function getShow()
    {
        return [
            'metadata' => [
                'name' => 'show',
                'description' => 'show module',
                'labels' => [
                    'app' => 'app-11',
                    'run' => 'flow-show-test',
                ],
            ],
            'modules' => [
                'image' => Config::$master . ':5000/show',
                'containerPort' => 80,
                'denySource' => ['comp', 'datastore',],
                'denyDestination' => ['access'],
                'volumeMounts' => [
                    ['name' => 'data', 'mountPath' => '/data'],
                    ['name' => 'error-log', 'mountPath' => '/var/log/httpd/error_log'],
                ],
            ],
        ];
    }

    // Utilities.
    public static function getModules()
    {
        return [ self::getAccess(), self::getIfthenDummy(), self::getShow() ];
    }
    public function registerModule($module)
    {
        return $this->post(null, $module);
    }
    public function registerModules()
    {
        $modules = self::getModules();
        foreach ($modules as $module){
            $this->registerModule($module);
        }
    }
    public function deleteModule($name)
    {
        return $this->delete($name);
    }

    public function registerModuleWithNonexistentImage($name)
    {
        $module = self::getAccess();
        $module['metadata']['name'] = $name;
        $module['modules']['image'] = Config::$master . ":5000/noexistance";
        return $this->registerModule($module);
    }
    public function registerModuleWithMaxLengthName()
    {
        $module = self::getAccess();
        $module['metadata']['name'] = '012345678012345678012345678012345678012345678999990123456789012';
        return $this->registerModule($module);
    }

    // Assertions.

    // Tests.

    public function testCreateModule()
    {
        $module = self::getAccess();
        $result = $this->registerModule($module);
        $this->assertArrayNotHasKey('error', $result, json_encode($result));
    }
    public function testCreateModuleWithNoName()
    {
        $module = self::getAccess();
        unset($module['metadata']['name']);
        $result = $this->registerModule($module);
        $this->assertArrayHasKey('error', $result, json_encode($result));
    }
    public function testCreateModuleWithNameLengthExceed()
    {
        $module = self::getAccess();
        $module['metadata']['name'] = '0123456780123456780123456780123456780123456789999901234567890123';
        $result = $this->registerModule($module);
        $this->assertArrayHasKey('error', $result, json_encode($result));
    }
    public function testCreateModuleWithNameMaxLength()
    {
        $result = $this->registerModuleWithMaxLengthName();
        $this->assertArrayNotHasKey('error', $result, json_encode($result));
        $this->deleteModule($result['metadata']['name']);
    }
    public function testCreateModuleWithExistName()
    {
        $result = $this->registerModule(self::getAccess());
        $result = $this->registerModule(self::getAccess());
        $this->assertArrayHasKey('error', $result, json_encode($result));

        self::deleteModule('access');
    }
    public function testCreateModuleWithNoImage()
    {
        $module = self::getAccess();
        unset($module['modules']['image']);
        $result = $this->registerModule($module);
        $this->assertArrayHasKey('error', $result, json_encode($result));
    }
    public function testCreateModuleWithNonexistentImage()
    {
        // call under conditions of all modules registered.
        $name = 'test';
        $result = $this->registerModuleWithNonexistentImage($name);
        // cannot detect errors here on thie time.
        $this->assertSame($name, $result['metadata']['name'] ?? "", json_encode($result));
        $this->deleteModule($name);
    }
    public function testCreateModuleWithNoContainerPort()
    {
        $module = self::getAccess();
        unset($module['modules']['containerPort']);
        $result = $this->registerModule($module);
        $this->assertArrayHasKey('error', $result, json_encode($result));
    }
    public function testCreateModuleWithIllegalContainerPort()
    {
        $module = self::getAccess();
        $module['modules']['containerPort'] = 'hoge';
        $result = $this->registerModule($module);
        $this->assertArrayHasKey('error', $result, json_encode($result));
    }
    public function testCreateModuleWithContainerPortOutOfRange()
    {
        $module = self::getAccess();
        $module['modules']['containerPort'] = 100000;
        $result = $this->registerModule($module);
        $this->assertArrayHasKey('error', $result, json_encode($result));
    }
}

