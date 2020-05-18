<?php

namespace Anaplam\Controller;

use Exception;
use RuntimeException;
use InvalidArgumentException;
use Anaplam\Model\RulesModel;
use Anaplam\Model\RulesFlow;
use Anaplam\Model\RulesFlowModel;
use Anaplam\Utility as Util;
use Anaplam\ResourceNotFoundException;

class RulesFlowController extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new RulesFlowModel();
    }
    public function _getFlow($request, $response, $args)
    {
        $this->init('get rule flow');
        $id = $this->getId($args);
        $flow_id = array_key_exists('flowNameSub', $args) ? (new FlowController())->getId($args['flowNameSub']) : null;
        $res = $this->model->get($id, $flow_id);
        echo yaml_emit($res);
        $this->syslog('success, flow "' . $res['metadata']['name'] . '"');
    }
    public function _createFlow($request, $response, $args)
    {
        $this->init('create rule flow');
        $info = $this->yaml_parse($request->getBody());
        $id = $this->model->create($info);
        $res = $this->model->get($id);
        echo yaml_emit($res);
        $this->syslog('success, flow "' . $res['metadata']['name'] . '"');
    }
    public function _deleteFlow($request, $response, $args)
    {
        $this->init($sit = "delete rule flow");
        $name = $args['flowName'];
        $id = $this->getId($args);
        if (!$id){
            throw new ResourceNotFoundException('flow', $name);
        }
        $this->model->delete($id);
        echo yaml_emit([$sit => "success", 'name' => $name]);
        $this->syslog('"'. $name . '" success');
    }
    public function _getFlows($request, $response, $args)
    {
        $this->init("get rule flows");
        $flows = $this->model->getAll();
        if (empty($flows)) {
            throw new RuntimeException("rule flow empty");
        }
        foreach ($flows as $flow) {
            echo yaml_emit($flow, YAML_UTF8_ENCODING);
        }
    }
    public function _updateFlow($request, $response, $args)
    {
        $this->init("update rule flow");
        $id = $this->getId($args);
        $info = $this->yaml_parse($request->getBody());
        (new RulesFlowModel())->update($id, $info);
        $res = $this->model->get($id);
        echo yaml_emit($res);
        $this->syslog(' success flow "' . $res['metadata']['name'] . '"');
    }
    public function _getRules($request, $response, $args)
    {
        $this->init("get rule");
        $id = $this->getId($args);
        echo yaml_emit($this->model->getRules($id));
    }
    public function _modifyRules($request, $response, $args)
    {
        $this->init("modify rule");
        $id = $this->getId($args);
        $info = $this->yaml_parse($request->getBody());
        $this->model->modify($id, $info);
        echo yaml_emit($this->model->getRules($id));
    }
    public function getId($args)
    {
        $name= $args['flowName'];
        $id = $this->model->getIdByName($name);
        if (empty($id)) {
            $id = $this->model->getIdByUuid($name);
            if (empty($id)) {
                throw new ResourceNotFoundException('flow', $name);
            }
        }
        return $id;
    }
}

