<?php

namespace Anaplam\Model;

use PDO;
use Anaplam\Model;
use Anaplam\Utility as Util;
use Anaplam\RequiredParamException;
use Anaplam\ResourceAlreadyExistException;
use Anaplam\ResourceDuplicateException;
use Anaplam\LengthExceedException;
use Anaplam\PodStatusException;

use Maclof\Kubernetes\Models\Pod;
use Maclof\Kubernetes\Models\Service;

class FlowModel extends Model
{
    protected static $name = "flow";

    /**
     * protected flow
     *
     * - name
     * - description
     *
     *
     * @var array
     */
    protected $flow = array();

    /**
     * protected labels
     *
     * - key => value
     *
     * @var array
     */
    protected $labels = array();

    /**
     * protected instances
     *
     * - instanceInfo obj
     *
     * @var array
     */
    protected $instances = array();

    /**
     * protected instance names
     *
     * - name
     *
     * @var array
     */
    protected $instanceNames = array();

    /**
     * construct
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * set flow info
     *
     * @param $flowInfo
     */
    public function setFlowInfo($flowInfo)
    {
        // flow
        if (isset($flowInfo['metadata']['name'])) {
            $this->flow['name'] = $flowInfo['metadata']['name'];
        }
        if (isset($flowInfo['metadata']['description'])) {
            $this->flow['description'] = $flowInfo['metadata']['description'];
        }
        // label
        if (isset($flowInfo['metadata']['labels'])) {
            if (is_array($flowInfo['metadata']['labels'])) {
                foreach ($flowInfo['metadata']['labels'] as $key => $value) {
                    $this->labels[$key] = $value;
                }
            }
        }
        // module instances
        if (is_array($flowInfo['flow'])) {
            foreach ($flowInfo['flow'] as $instance) {
                $infstanceInfo = static::createModuleInstanceModel();
                $infstanceInfo->setInstanceInfo($instance);
                $this->instances[] = $infstanceInfo;
                // for duplicate check
                $this->instanceNames[] = $instance['name'];
            }
        }
    }
    protected static function createModuleInstanceModel()
    {
        return new ModuleInstanceModel();
    }

    /**
     * valid
     *
     * @param update
     *
     */
    public function valid($update = false)
    {
        Util::kubernetesLabelCheck(static::$name, 'name', $this->flow['name'] ?? null);

        // maxlength
        $maximum = 63;
        if (strlen($this->flow['name']) > $maximum){
            throw new LengthExceedException(static::$name, 'name', $this->module['name'], $maximum);
        }
        // name duplicate
        if (!$update && $this->getIdByName($this->flow['name'])){
            throw new ResourceAlreadyExistException(static::$name, 'name', $this->flow['name']);
        }
        // flow instance name duplicate check
        $instanceNames = []; 
        foreach ($this->instanceNames as $instanceName) {
            if (isset($instanceNames[$instanceName])) {
                throw new ResourceDuplicateException(static::$name, 'name', $instanceName);
            } else {
                $instanceNames[$instanceName] = 1;
            }
        }
        // instance valid
        $checkInstances = $this->instances;
        foreach ($this->instances as $pos => $instance) {
            $instance->valid($update);
        }
        $this->topologyCheck();
    }
    protected function topologyCheck()
    {
        $moduleModel = new ModuleModel();
        foreach ($this->instances as $instance){
            // create name-dsts map.
            $name = $instance->getInfoInstance()['name'];
            $dsts = $instance->getInfoDsts();

            // get module denyDistination.
            $moduleName = $instance->getInfoInstance()['module'];
            $denyDsts = $moduleModel->getDenyDsts($moduleModel->getIdByName($moduleName)) ?? [];
            #Util::syslog($moduleName . " denyDestination => " . json_encode($denyDsts));

            // Check if it is permitted as source by the module and the module instance specified as destination.
            foreach ($dsts as $dst){
                foreach ($this->instances as $i){
                    if (strcmp($i->getInfoInstance()['name'], $dst) == 0){
                        // destination is not in source.
                        $srcs = $i->getInfoSrcs();
                        if (!empty($srcs) && !in_array($name, $srcs)){
                            $msg = "'" . $name . "' is not allowed as a source of '" . $dst . "' by the module instance's `source`.";
                            throw new \RuntimeException($msg);
                        }
                        // destination is not allowed by module denyDestination.
                        $dstModuleName = $i->getInfoInstance()['module'];
                        if (in_array($dstModuleName, $denyDsts)){
                            $msg = "'" . $name . "' is not allowed as a source of '" . $dst . "' by the module's `denyDestination`.";
                            throw new \RuntimeException($msg);
                        }
                        // source is not allowed by module denySource.
                        $denySrcs = $moduleModel->getDenySrcs($moduleModel->getIdByName($dstModuleName)) ?? [];
                        #Util::syslog($dstModuleName . " denySource => " . json_encode($denySrcs));
                        if (in_array($moduleName, $denySrcs)){
                            $msg = "'" . $name . "' is not allowed as a source of '" . $dst . "' by the module's `denySource`.";
                            throw new \RuntimeException($msg);
                        }
                        break;
                    }
                }
            }
        }
    }


    /**
     * create
     *
     */
    public function create()
    {
        $id = false;
        $timeStamp = Util::getTimeStamp();
        $this->db->beginTransaction();
        try {
            // flow
            $id = $this->__createFlow($id, $timeStamp);
            if (!$id) {
                $this->db->rollBack();
                return false;
            }
            // label
            if (!$this->createLabels($id, $timeStamp)) {
                $this->db->rollBack();
                return false;
            }
            // instance
            if (!$this->createInstances($id, $timeStamp)) {
                $this->db->rollBack();
                return false;
            }
            // create pod
            $moduleInstance = static::createModuleInstanceModel();
            $instances = $moduleInstance->getAllInstance($id);
            foreach ($instances as $instance) {
                $flow = $this->flow;
                $flow['labels'] = $this->labels ?? [];
                if (!$this->k8s->createInstance($instance['id'], null, $flow)) {
                    $this->db->rollBack();
                    // TODO: delete pods created already.
                    return false;
                }
            }
            // post config
            foreach ($instances as $instance) {
                // create post config
                $config = $moduleInstance->getConfigData($id, $instance['id'], $this->flow);
                // get url
                /* 
                // flannel
                $url = $this->k8s->getPodIp($instance['id']);
                */
                // nodePort
                // URL = nodeIP:nodePort/config
                $url = $moduleInstance->getInstanceUrl($instance['flow_id'], $instance['name'], true) . '/config';
                // post config
                $cmd = "curl -sk -o /dev/null -w '%{HTTP_CODE}' -X POST -d '" . $config . "' " . $url;
                // XXX
                // exec curl
                $ret = exec($cmd);
                if ($ret == '200') {
                    $msg = 'post success ' . $config;
                    Util::syslog($msg);
                } else {
                    $msg = 'post failed ' . $config;
                    Util::syslog($msg);
                    $this->db->rollBack();
                    // TODO: delete pods created already.
                    return false;
                }
            }
            $this->db->commit();
        } catch (PodStatusException $e){
            $this->db->commit();
            throw $e;
        } catch (Exception $e){
            $this->db->rollBack();
            throw $e;
        }
        return $id;
    }

    /**
     * create flow
     *
     */
    private function __createFlow($id, $timeStamp)
    {
        $sql = 'INSERT INTO `flows` (`name`, `description`, `uuid`, `created_at`, `updated_at`) VALUES (:name, :description, :uuid, :created_at, :updated_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmt->bindParam(':created_at', $created_at, PDO::PARAM_STR);
        $stmt->bindParam(':updated_at', $updated_at, PDO::PARAM_STR);
        // set param
        if (isset($this->flow['name'])) {
            $name = $this->flow['name'];
        }
        if (isset($this->flow['description'])) {
            $description = $this->flow['description'];
        } else {
            $description = null;
        }
        $uuid = Util::createUUID();
        $created_at = $timeStamp; 
        $updated_at = $timeStamp;
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        // set flow info
        $this->flow = array('name' => $name, 'description' => $description, 'uuid' => $uuid);
        return $this->db->lastInsertId();
    }

    /**
     * create label
     *
     */
    protected function createLabels($id, $timeStamp)
    {
        if (empty($this->labels)) {
            return true;
        }
        $sql = "INSERT INTO `flow_labels` (`flow_id`, `key`, `value`, `created_at`, `updated_at`) VALUES (:flow_id, :key, :value, :created_at, :updated_at)";
        foreach($this->labels as $key => $value) {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':flow_id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':key', $key, PDO::PARAM_STR);
            $stmt->bindParam(':value', $value, PDO::PARAM_STR);
            $stmt->bindParam(':created_at', $timeStamp, PDO::PARAM_STR);
            $stmt->bindParam(':updated_at', $timeStamp, PDO::PARAM_STR);
            try {
                $ret = $stmt->execute();
                if (!$ret) {
                    return false;
                }
            } catch (PDOException $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * create instances
     *
     */
    protected function createInstances($id, $timeStamp)
    {
        $ret = true;
        $seqId = 1;
        foreach ($this->instances as $instance) {
            if (!$instance->create($id, $timeStamp, $seqId)) {
                $ret = false;
            }
            $seqId = $seqId + 1;      
        }
        return $ret;     
    }

    /**
     * delete
     *
     * @param $id
     * @return bool
     */
    public function delete($id, $db_only = false)
    {
        $this->db->beginTransaction();
        try {
            // flow
            $sql = 'DELETE FROM `flows` WHERE `id` = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            // flow labels
            $sql = 'DELETE FROM `flow_labels` WHERE `flow_id` = :flow_id';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':flow_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            // instances
            $instance = static::createModuleInstanceModel();
            $instance->delete($id, $db_only);
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->db->commit();
    }

    /**
     * update
     *
     * @param $id
     * @param $updateFlowInfo
     * @param bool
     */
    public function update($id, $updateFlowInfo)
    {
        $update = true;
        $flowInfo = $this->getFlowInfo($id);
        $flow = $flowInfo['flow'];
        $updateFlow = $updateFlowInfo['flow'];

        // same instance count check
        if (count($flow) != count($updateFlow)) {
            $msg = $flowInfo['metadata']['name'] . ' module instances are in conflict with at count.';
            $this->setErrors($msg);
            Util::syslog($msg);
            $update = false;
        }

        // check => same name and same module name
        foreach ($flow as $count => $instance) {
            if ($instance['name'] != $updateFlow[$count]['name']) {
                $msg = 'module instance "' . $updateFlow[$count]['name'] . '" is in conflict with at name.';
                $this->setErrors($msg);
                Util::syslog($msg);
                $update = false;

            }
            if ($instance['module'] != $updateFlow[$count]['module']) {
                $msg = 'module instance "' . $updateFlow[$count]['name'] . '" is in conflict with at module.';
                $this->setErrors($msg);
                Util::syslog($msg);
                $update = false;
            }
        }

        if ($update) {
            foreach ($flow as $count => $instance) {
                if ($instance['config']['params'] != $updateFlow[$count]['config']['params']) {
                    $moduleInstance = static::createModuleInstanceModel();
                    $ret = $moduleInstance->updateParams($id, $updateFlow[$count]['name'], $updateFlow[$count]['config']['params']);
                    if (!$ret) {
                        $msg = 'module instance "' . $updateFlow[$count]['name'] . '" is failed update params.';
                        $this->setErrors($msg);
                        Util::syslog($msg);
                        $update = false;
                    }
                }
            }
        }
        return $update;
    }

    /**
     * get
     *
     * @param id
     *
     */
    public function get($id)
    {
        $sql = 'SELECT * FROM `flows` WHERE `id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res;
    }

    /**
     * get id by name
     *
     */
    public function getIdByName($name)
    {
        $sql = 'SELECT * FROM `flows` WHERE `name` = :name';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res['id'];
    }

    /**
     * get id by uuid
     *
     */
    public function getIdByUuid($uuid)
    {
        $sql = 'SELECT * FROM `flows` WHERE `uuid` = :uuid';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res['id'];
    }

    /**
     * get flow Info
     *
     */
    public function getFlowInfo($id)
    {
        $flowInfo = array();
        $flow = array();
        $labels = array();
        $instances = array();
        // flow 
        $sql = 'SELECT * FROM `flows` WHERE `id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $flow = $stmt->fetch(PDO::FETCH_ASSOC);
        // name        
        $flowInfo['metadata']['name'] = $flow['name'];
        // description   
        if (!empty($flow['description'])) {
            $flowInfo['metadata']['description'] = $flow['description'];
        }
        // labels
        $sql = 'SELECT * FROM `flow_labels` WHERE `flow_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $flwoLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($flwoLabels as $flwoLabel) {
            $labels[$flwoLabel['key']] = $flwoLabel['value'];
        }
        if (!empty($labels)) {
            $flowInfo['metadata']['labels'] = $labels;
        }
        // uuid
        $flowInfo['metadata']['uuid'] = $flow['uuid'];
        // creation Timestamp
        $flowInfo['metadata']['creationTimestamp'] = date(DATE_ISO8601, strtotime($flow['created_at']));
        // lastModifiedTimestamp
        $flowInfo['metadata']['lastModifiedTimestamp'] = date(DATE_ISO8601, strtotime($flow['updated_at']));
        // instances
        $instance = static::createModuleInstanceModel();
        $flowInfo['flow'] = $instance->getAllInstanceInfo($id);
        return $flowInfo;
    }

    /**
     * get all flow info
     *
     *
     */
    public function getAllFlowInfo()
    {
        $flowInfos = array();
        $sql = 'SELECT * FROM `flows`';
        $flows = $this->db->query($sql);
        foreach($flows as $flow) {
            $flowInfos[] = $this->getFlowInfo($flow['id']);
        }        
        return $flowInfos;        
    }
}

