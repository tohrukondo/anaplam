<?php

require './vendor/autoload.php';

class AnaplamAPI
{
    /**
     * public slim
     *
     */
    public $slim;

    /**
     * construct
     *
     */
    public function __construct()
    {
        $container = new \Slim\Container();
        /* debug mode */
        // $container['settings']['displayErrorDetails'] = true;
        $container['notFoundHandler'] = function ($container) {
            return function ($request, $response) use ($container) {
                return $container['response']
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/x-yaml')
                    ->write(yaml_emit(array('errors' => array('routing error.'))));
            };
        };
        $this->slim = new Slim\App($container);
        // API KEY Auth
        $this->slim->add(new Anaplam\Auth(['v1', 'v2']));
        $this->_setRouting();
    }

    /**
     * set Routing
     *
     */
    private function _setRouting()
    {
        //test
        $this->slim->get('/', array($this, 'getRoot'));
        // Slack slash command API
        $this->slim->group('/slack', function() {
            $this->post('/get_api_key', [new Anaplam\Slack(), 'get_api_key']);
            $this->post('/update_api_key', [new Anaplam\Slack(), 'update_api_key']);
            $this->post('/create_join_command', [new Anaplam\Slack(), 'create_join_command']);
        });
        $this->slim->group('/v1', function() {
            // get nodes
            $this->group('/nodes', function() {
                $this->get('[/]', array(new Anaplam\Controller\NodeController(), 'v1GetNodes'));
                $this->get('/{location}', array(new Anaplam\Controller\NodeController(), 'v1GetNode'));
            });
            // Module Control
            $this->group('/modules', function() {
                $this->get('[/]', array(new Anaplam\Controller\ModuleController(), 'v1GetModules'));
                $this->get('/{moduleName}', array(new Anaplam\Controller\ModuleController(), 'v1GetModule'));
                $this->post('[/]', array(new Anaplam\Controller\ModuleController(), 'v1CreateModule'));
                $this->post('/{moduleName}', array(new Anaplam\Controller\ModuleController(), 'v1UpdateModule'));
                $this->delete('/{moduleName}', array(new Anaplam\Controller\ModuleController(), 'v1DeleteModule'));
            });
            // Flow Control
            $this->group('/flows', function() {
                $this->get('[/]', array(new Anaplam\Controller\FlowController(), 'v1GetFlows'));
                $this->get('/{flowName}', array(new Anaplam\Controller\FlowController(), 'v1GetFlow'));
                $this->post('[/]', array(new Anaplam\Controller\FlowController(), 'v1CreateFlow'));
                $this->post('/{flowName}', array(new Anaplam\Controller\FlowController(), 'v1UpdateFlow'));
                $this->delete('/{flowName}', array(new Anaplam\Controller\FlowController(), 'v1DeleteFlow'));
                // Module Instance
                $this->get('/{flowName}/{instanceName}', array(new Anaplam\Controller\ModuleInstanceController(), 'v1GetModuleInstance'));
            });
        });
        $this->slim->group('/v2', function() {
            // get nodes
            $this->group('/nodes', function() {
                $this->get('[/]', array(new Anaplam\Controller\NodeController(), 'v1GetNodes'));
                $this->get('/{location}', array(new Anaplam\Controller\NodeController(), 'v1GetNode'));
            });
            // Module Control
            $this->group('/modules', function() {
                $this->get('[/]', array(new Anaplam\Controller\ModuleController(), 'v1GetModules'));
                $this->get('/{moduleName}', array(new Anaplam\Controller\ModuleController(), 'v1GetModule'));
                $this->post('[/]', array(new Anaplam\Controller\ModuleController(), 'v1CreateModule'));
                $this->post('/{moduleName}', array(new Anaplam\Controller\ModuleController(), 'v1UpdateModule'));
                $this->delete('/{moduleName}', array(new Anaplam\Controller\ModuleController(), 'v1DeleteModule'));
            });
            // Flow Control
            $this->group('/flows', function() {
                $this->get('[/]', array(new Anaplam\Controller\RulesFlowController(), 'getFlows'));
                $this->get('/{flowName}', array(new Anaplam\Controller\RulesFlowController(), 'getFlow'));
                $this->get('/{flowName}/{flowNameSub}', array(new Anaplam\Controller\RulesFlowController(), 'getFlow'));
                $this->post('[/]', array(new Anaplam\Controller\RulesFlowController(), 'createFlow'));
                $this->post('/{flowName}', array(new Anaplam\Controller\RulesFlowController(), 'updateFlow'));
                $this->delete('/{flowName}', array(new Anaplam\Controller\RulesFlowController(), 'deleteFlow'));
                // Module Instance
                $this->get('/{flowName}/{flowNameSub}/{instanceName}', array(new Anaplam\Controller\ModuleInstanceController(), 'v2GetModuleInstance'));
            });
            // Rules
            $this->group('/rules', function() {
                $this->get('/{flowName}', array(new Anaplam\Controller\RulesFlowController(), 'getRules'));
                $this->post('/{flowName}', array(new Anaplam\Controller\RulesFlowController(), 'modifyRules'));
            });
        });
    }

    /**
     * get root
     *
     */
    public function getRoot()
    {
        echo 'Hello Anaplam Controller!';
        return;
    }

    /**
     * run
     */
    public function run()
    {
        $this->slim->run();
    }
}

$api = new AnaplamAPI();
$api->run();

