<?php

namespace Anaplam\Controller;

use Anaplam\Model\ModuleModel;
use Anaplam\Utility as Util;
use Anaplam\ResourceNotFoundException;

class ModuleController extends BaseController
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
        $this->model = new ModuleModel();
    }

    public function getId($name)
    {
        $moduleId = $this->model->getIdByName($name);
        if (empty($moduleId)) {
            // uuid
            $moduleId = $this->model->getIdByUuid($name);
            if (empty($moduleId)) {
                throw new ResourceNotFoundException('module', $name);
            }
        }
        return $moduleId;
    }

    /**
     * v1 get module
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1GetModule($request, $response, $args)
    {
        $this->init("get module");
        echo yaml_emit($this->model->getModuleInfo($this->getId($args['moduleName'])));
    }

    /**
     * v1 create module
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1CreateModule($request, $response, $args)
    {
        $this->init("create module");
        $this->model->setModuleInfo($this->yaml_parse($request->getBody()));
        $this->model->valid();
        echo yaml_emit($this->model->getModuleInfo($this->model->create()));
    }

    /**
     * v1 delete module
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1DeleteModule($request, $response, $args)
    {
        $this->init($sit = "delete module");
        $this->model->delete($this->getId($name = $args['moduleName']));
        echo yaml_emit([$sit => "success", 'name' => $name]);
    }

    /**
     * v1 get modules
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1GetModules($request, $response, $args)
    {
        $this->init("get modules");
        $modules = $this->model->getAllModuleInfo();
        foreach ($modules as $module) {
            echo yaml_emit($module, YAML_UTF8_ENCODING);
        }
    }

    /**
     * v1 update module
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function _v1UpdateModule($request, $response, $args)
    {
        $this->init("update module");
        $this->model->setModuleInfo($this->yaml_parse($request->getBody()));
        $this->model->valid(true);
        echo yaml_emit($this->model->getModuleInfo($this->model->update($this->getId($args['moduleName']))));
    }
}

