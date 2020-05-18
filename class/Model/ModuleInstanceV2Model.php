<?php

namespace Anaplam\Model;

use PDO;
use RuntimeException;
use Anaplam\Model\ModuleInstanceModel;
use Anaplam\Utility as Util;
use Anaplam\ResourceNotFoundException;
use Anaplam\RequiredParamException;
use Anaplam\PodStatusException;

use Maclof\Kubernetes\Models\Pod;

class ModuleInstanceV2Model extends ModuleInstanceModel
{
    /**
     * construct
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * create
     *
     * @param flowId
     * @param timestamp
     * @param seqId
     */
    public function create($flowId, $timeStamp, $seqId)
    {
        return parent::create($flowId, $timeStamp, $seqId);
    }

    public function activateModule($rfinfo, $finfo, $instance)
    {
        try {
            if (!$this->k8s->createInstance($instance['id'], $rfinfo['metadata'], $finfo['metadata'])){
                throw new \RuntimeException("can not create pod. " . $instance['instance_name']);
            }
        } catch (PodStatusException $e){
            $this->deactivateModule($instance['id']);
            throw $e;
        }
    }
    public function deactivateModule($id)
    {
        $instances = $this->getAllInstance($id);
        foreach ($instances as $instance){
            if (!$this->k8s->deleteInstance($instance['uuid'])){
                throw new \RuntimeException('delete failed kubernetes pod and service. instance name = ' . $instance['instance_name']);
            }
        }
    }
}

