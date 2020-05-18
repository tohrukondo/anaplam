<?php

namespace Anaplam\Controller;

use Anaplam\Model\ModuleInstanceModel;
use Anaplam\Model\ModuleInstanceV2Model;
use Anaplam\Model\FlowModel;
use Anaplam\Model\FlowV2Model;
use Anaplam\Model\RulesFlowModel;
use Anaplam\ResourceNotFoundException;
use Anaplam\Utility as Util;

class ModuleInstanceController extends BaseController
{
    /**
     * construct
     */
    public function __construct()
    {
    }

    /**
     * v1 get module instance
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1GetModuleInstance($request, $response, $args)
    {
        $this->init("get module instance");

        $flowName = $args['flowName'];
        $flow_id = (new FlowModel())->getIdByName($flowName);
        if (empty($flow_id)){
            throw new ResourceNotFoundException('flow', $flowName);
        }
        $model = new ModuleInstanceModel();
        $instanceName = $args['instanceName'];
        $module_id = $model->getIdByFlowIdAndName($flow_id, $instanceName);
        if (empty($module_id)){
            throw new ResourceNotFoundException('module instance', $instanceName);
        }
        echo yaml_emit($model->getInstanceInfo($module_id), YAML_UTF8_ENCODING);
    }
    public function _v2GetModuleInstance($request, $response, $args)
    {
        $this->init("get module instance");

        $flowName = $args['flowName'];
        $flowNameSub = $args['flowNameSub'];
        $instanceName = $args['instanceName'];

        $id = (new RulesFlowModel())->getIdByName($flowName);
        if (empty($id)){
            throw new ResourceNotFoundException('rules flow', $flowName);
        }
        $flow_id = (new FlowV2Model($id))->getIdByName($flowNameSub);
        if (empty($flow_id)){
            throw new ResourceNotFoundException('flow', $flowNameSub);
        }
        $model = new ModuleInstanceV2Model();
        $module_id = $model->getIdByFlowIdAndName($flow_id, $instanceName);
        if (empty($module_id)){
            throw new ResourceNotFoundException('module instance', $instanceName);
        }
        echo yaml_emit($model->getInstanceInfo($module_id), YAML_UTF8_ENCODING);
    }
}

