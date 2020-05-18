<?php

namespace Anaplam\Controller;

use Anaplam\Model\FlowModel;
use Anaplam\Utility as Util;
use Anaplam\ResourceNotFoundException;

class FlowController extends BaseController
{
    /**
     * protected model
     *
     */
    protected $model;

    /**
     * construct
     *
     */
    public function __construct()
    {
        $this->model = new FlowModel();
    }

    public function getId($name)
    {
        $id = $this->model->getIdByName($name);
        if (empty($id)) {
            $id = $this->model->getIdByUuid($name);
            if (empty($id)) {
                throw new ResourceNotFoundException('flow', $name);
            }
        }
        return $id;
    }

    /**
     * v1 get flow
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1GetFlow($request, $response, $args)
    {
        $this->init("get flow");
        echo yaml_emit($this->model->getFlowInfo($this->getId($args['flowName'])));
    }

    /**
     * v1 create flow
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1CreateFlow($request, $response, $args)
    {
        $this->init("create flow");
        # set flow Info
        $this->model->setFlowInfo($this->yaml_parse($request->getBody()));
        # flow Valid
        $this->model->valid();
        # flow create
        echo yaml_emit($this->model->getFlowInfo($this->model->create()));
    }

    /**
     * v1 delete flow
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1DeleteFlow($request, $response, $args)
    {
        $this->init("delete flow");
        $this->model->delete($this->model->getIdByName($args['flowName']));
    }

    /**
     * v1 get flows
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1GetFlows($request, $response, $args)
    {
        $this->init("get flows");
        $flows = $this->model->getAllFlowInfo();
        foreach ($flows as $flow) {
            echo yaml_emit($flow, YAML_UTF8_ENCODING);
        }
    }

    /**
     * v1 update flow
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1UpdateFlow($request, $response, $args)
    {
        $this->init("update flow");
        $flowId = $this->model->getIdByName($args['flowName']);
        # flow (module instance param) update
        $this->model->update($flowId, $this->yaml_parse($request->getBody()));
        echo yaml_emit($this->model->getFlowInfo($flowId));
    }
}

