<?php

namespace Anaplam\Model;

use PDO;
use Anaplam\Model;
use Anaplam\Utility as Util;
use Anaplam\ResourceNotFoundException;
use Anaplam\RequiredParamException;

use Maclof\Kubernetes\Models\Pod;

class ModuleInstanceModel extends Model
{
    protected static $name = 'module instance';

    /**
     * protected instance
     *
     * - name
     * - instance_name
     * - module
     * - flow 
     * - location
     * - url
     * - debug
     * - seq
     *
     * @var array
     */
    protected $instance = array();

    /**
     * protected srcs
     *
     * - name
     *
     * @var array
     */
    protected $srcs = array();

    /**
     * protected dsts
     *
     * - name
     *
     * @var array
     */
    protected $dsts = array();

    /**
     * protected params
     *
     * - key => value
     *
     * @var array
     */
    protected $params = array();

    protected $volumes = [];

    /**
     * construct
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * set instance info
     *
     * @param $instanceInfo
     */
    public function setInstanceInfo($instanceInfo)
    {
        // instance
        if (isset($instanceInfo['name'])) {
            $this->instance['name'] = $instanceInfo['name'];
        }
        if (isset($instanceInfo['module'])) {
            $this->instance['module'] = $instanceInfo['module'];
        }
        if (isset($instanceInfo['location'])) {
            $this->instance['location'] = $instanceInfo['location'];
        }
        if (isset($instanceInfo['debug'])) {
            $this->instance['debug'] = $instanceInfo['debug'];
        }
        // srcs
        if (isset($instanceInfo['config']['source'])) {
            if (is_array($instanceInfo['config']['source'])) {
                foreach ($instanceInfo['config']['source'] as $key => $value) {
                    $this->srcs[] = $value;
                }
            }
        }
        // dsts
        if (isset($instanceInfo['config']['destination'])) {
            if (is_array($instanceInfo['config']['destination'])) {
                foreach ($instanceInfo['config']['destination'] as $key => $value) {
                    $this->dsts[] = $value;
                }
            }
        }
        // params
        if (isset($instanceInfo['config']['params'])) {
            if (is_array($instanceInfo['config']['params'])) {
                foreach ($instanceInfo['config']['params'] as $key => $value) {
                    $this->params[$key] = json_encode($value);
                }
            }
        }
        // volumes
        $this->volumes = $instanceInfo['volumes'] ?? null;
        #Util::syslog("instance = " . json_encode($this->instance));
    }

    /**
     * get info srcs
     *
     * @return srcs
     */
    public function getInfoInstance()
    {
        return $this->instance;
    }

    /**
     * get info srcs
     *
     * @return srcs
     */
    public function getInfoSrcs()
    {
        return $this->srcs;
    }

    /**
     * get info dsts
     *
     * @return dsts
     */
    public function getInfoDsts()
    {
        return $this->dsts;
    }

    /**
     * is name
     *
     * @param $name
     */
    public function isName($name)
    {
        if ($this->instance['name'] == $name) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * get Module
     *
     * @return module obj
     */
    public function getModule()
    {
        $moduleModel = new ModuleModel();
        $module_id = $moduleModel->getIdByName($this->instance['module']);
        return $moduleModel->get($module_id);
    }    

    /**
     * getModuleDenySrcs
     *
     * @return module deny srcs
     */
    public function getModuleDenySrcs()
    {
        $moduleModel = new ModuleModel();
        $module_id = $moduleModel->getIdByName($this->instance['module']);
        return $moduleModel->getDenySrcs($module_id);
    }


    /**
     * getModuleDenyDsts
     *
     * @return module deny dsts
     */
    public function getModuleDenyDsts()
    {
        $moduleModel = new ModuleModel();
        $module_id = $moduleModel->getIdByName($this->instance['module']);
        return $moduleModel->getDenyDsts($module_id);
    }

    /**
     * valid
     *
     * @param $update
     *
     */
    public function valid($update = false)
    {
        $module = new ModuleModel();

        // name check.
        Util::kubernetesLabelCheck(static::$name, 'name', $this->instance['name']);

        // module exsits Chek
        if (empty($this->instance['module'])) {
            throw new RequiredParamException(static::$name, 'module');
        } else if (!$module->getIdByName($this->instance['module'])){
            throw new ResourceNotFoundException('module', $this->instance['module']);
        }
        // node exists Check
        if (isset($this->instance['location'])) {
            Util::kubernetesLabelCheck(static::$name, 'location', $this->instance['location']);
            $node = $this->k8s->getNode($this->instance['location']);
            if (empty($node)) {
                throw new ResourceNotFoundException('location', $this->instance['location']);
            }
        }
        foreach($this->volumes ?? [] as $volume){
            Util::kubernetesLabelCheck(static::$name, 'volume name', $volume['name']);
        }
    }

    /**
     * create
     *
     * @param flowId
     * @param timestamp
     * @param seqId
     */
    public function create($flwoId, $timeStamp, $seqId)
    {
        // instance
        $instanceId = $this->createInstance($flwoId, $timeStamp, $seqId);
        if (!$instanceId) {
            return false;
        }
        // srcs
        if (!$this->createSrcs($instanceId, $timeStamp)) {
            return false;
        }
        // dsts
        if (!$this->createDsts($instanceId, $timeStamp)) {
            return false;
        }
        // params
        if (!$this->createParams($instanceId, $timeStamp)) {
            return false;
        }
        // volumes
        if (!$this->createVolumes($instanceId, $timeStamp)) {
            return false;
        }
        return true;
    }

    public static function createInstanceName($name, $uuid)
    {
        return 'anaplam-' . $uuid;
    }

    /**
     * create instance
     *
     */
    private function createInstance($flow_id, $timeStamp, $seq)
    {
        $sql = 'INSERT INTO `module_instances` (`name`, `instance_name`, `uuid`, `module_id`, `flow_id`, `location`, `url`, `debug`, `seq`, `created_at`, `updated_at`) VALUES (:name, :instance_name, :uuid, :module_id, :flow_id, :location, :url, :debug, :seq, :created_at, :updated_at)';
        $stmt = $this->db->prepare($sql);
        // set param
        // name
        if (isset($this->instance['name'])) {
            $name = $this->instance['name'];
        }        
        // instance name
        // uuid
        $uuid = Util::createUUID();
        $instance_name = self::createInstanceName($name, $uuid);
        // module_id
        $module = new ModuleModel();
        $module_id = $module->getIdByName($this->instance['module']);
        // flow_id
        // location
        $location = $this->instance['location'] ?? null;
        // url
        $url = $this->instance['url'] ?? null;
        // debug
        $debug = $this->insntace['debug'] ?? 0;
        if ($debug == 1 || $debug == true || $debug == 'on') {
            $debug = 1;
        } else {
            $debug = 0;
        }
        // seq
        $created_at = $timeStamp;
        $updated_at = $timeStamp;

        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':instance_name', $instance_name);
        $stmt->bindValue(':uuid', $uuid);
        $stmt->bindValue(':module_id', $module_id, PDO::PARAM_INT);
        $stmt->bindValue(':flow_id', $flow_id, PDO::PARAM_INT);
        $stmt->bindValue(':location', $location);
        $stmt->bindValue(':url', $url);
        $stmt->bindValue(':debug', $debug, PDO::PARAM_INT);
        $stmt->bindValue(':seq', $seq, PDO::PARAM_INT);
        $stmt->bindValue(':created_at', $created_at);
        $stmt->bindValue(':updated_at', $updated_at);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        return $this->db->lastInsertId();
    }

    /**
     * create srcs
     *
     */
    private function createSrcs($id, $timeStamp)
    {
        if (empty($this->srcs)) {
            return true;
        }
        $sql = 'INSERT INTO `module_instance_sources` (`module_instance_id`, `name`, `created_at`, `updated_at`) VALUES (:module_instance_id, :name, :created_at, :updated_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindValue(':module_instance_id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':created_at', $timeStamp);
        $stmt->bindValue(':updated_at', $timeStamp);
        foreach($this->srcs as $name) {
            if (!$stmt->execute()){
                assert(false);
                return false;
            }
        }
        return true;
    }

    /**
     * create dsts
     *
     */
    private function createDsts($id, $timeStamp)
    {
        if (empty($this->dsts)) {
            return true;
        }

        $sql = 'INSERT INTO `module_instance_destinations` (`module_instance_id`, `name`, `created_at`, `updated_at`) VALUES (:module_instance_id, :name, :created_at, :updated_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindValue(':module_instance_id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':created_at', $timeStamp);
        $stmt->bindValue(':updated_at', $timeStamp);
        foreach($this->dsts as $name) {
            if (!$stmt->execute()){
                assert(false);
                return false;
            }
        }
        return true;
    }

    /**
     * create params
     *
     */
    private function createParams($id, $timeStamp)
    {
        if (empty($this->params)) {
            return true;
        }
        $sql = 'INSERT INTO `module_instance_params` (`module_instance_id`, `key`, `value`, `created_at`, `updated_at`) VALUES (:module_instance_id, :key, :value, :created_at, :updated_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':module_instance_id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->bindValue(':created_at', $timeStamp);
        $stmt->bindValue(':updated_at', $timeStamp);
        foreach($this->params as $key => $value) {
            if (!$stmt->execute()){
                assert(false);
                return false;
            }
        }
        return true;
    }

    /**
     * create volumes
     *
     */
    private function createVolumes($id, $timeStamp)
    {
        if (empty($this->volumes)) {
            return true;
        }
        $sql = 'INSERT INTO `module_instance_volumes` (`module_instance_id`, `name`, `host_path`, `type`, `created_at`, `updated_at`) VALUES (:module_instance_id, :name, :host_path, :type, :created_at, :updated_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':module_instance_id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':host_path', $host_path);
        $stmt->bindParam(':type', $type);
        $stmt->bindValue(':created_at', $timeStamp);
        $stmt->bindValue(':updated_at', $timeStamp);
        foreach($this->volumes as $hash) {
            if (empty($hash['name']) || empty($hash['hostPath'])){
                continue;
            }
            $name = $hash['name'];
            $host_path = $hash['hostPath']['path'] ?? null;
            $type = $hash['hostPath']['type'] ?? null;
            if (!$stmt->execute()){
                assert(false);
                return false;
            }
        }
        return true;
    }

    /**
     * delete flow instances
     *
     * @param flow_id
     *
     */
    public function delete($flow_id, $db_only = false)
    {
        $instances = $this->getAllInstance($flow_id);
        foreach ($instances as $instance) {
            $this->_delete($instance, $db_only);
        }
    }

    /**
     * delete
     *
     * @param instance
     *
     */
    private function _delete($instance, $db_only = false)
    {
        $id = $instance['id'];
        if (!$db_only){
            $uuid = $instance['uuid'];
            if (!$this->k8s->deleteInstance($uuid)) {
                $msg = 'delete failed kubernetes pod and service. instance name = ' . $instance['instance_name'];
                $this->setErrors($msg);
                Util::syslog($msg);
                return false;
            }
        }
        // DB table
        // instance
        $sql = 'DELETE FROM `module_instances` WHERE `id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        // srcs
        $sql = 'DELETE FROM `module_instance_sources` WHERE `module_instance_id` = :module_instance_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':module_instance_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        // dsts
        $sql = 'DELETE FROM `module_instance_destinations` WHERE `module_instance_id` = :module_instance_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':module_instance_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        // params
        $sql = 'DELETE FROM `module_instance_params` WHERE `module_instance_id` = :module_instance_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':module_instance_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        // volumes
        $sql = 'DELETE FROM `module_instance_volumes` WHERE `module_instance_id` = :module_instance_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':module_instance_id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * update params
     *
     * @param flow_id
     * @param module instance name
     * @param module instance update params
     * @return bool
     */
    public function updateParams($flow_id, $name, $params)
    {
        $flowModel = new FlowModel();
        $flow = $flowModel->get($flow_id);

        $instance_id = $this->getIdByName($flow['name'], $name);
        $instance = $this->get($instance_id);

        $this->db->beginTransaction();
        try {
            // delete old params
            $sql = 'DELETE FROM `module_instance_params` WHERE `module_instance_id` = :module_instance_id';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':module_instance_id', $instance_id, PDO::PARAM_INT);
            if (!$stmt->execute()){
                assert(false);
                return false;
            }
            // create new params if params is not empty.
            $timeStamp = Util::getTimeStamp();
            $sql = 'INSERT INTO `module_instance_params` (`module_instance_id`, `key`, `value`, `created_at`, `updated_at`) VALUES (:module_instance_id, :key, :value, :created_at, :updated_at)';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':module_instance_id', $instance_id, PDO::PARAM_INT);
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->bindValue(':created_at', $timeStamp);
            $stmt->bindValue(':updated_at', $timeStamp);
            foreach($params as $key => $value) {
                $value = json_encode($value);
                if (!$stmt->execute()){
                    assert(false);
                    return false;
                }
            }
            $this->db->commit();
        } catch (Exception $e){
            $this->db->rollback();
            throw $e;
        }
        return $this->postConfig($flow_id, $flow, $instance);
    }

    /**
     * get
     *
     * @param obj
     *
     */
    public function get($id, $rules_flow_id = null)
    {
        $sql = 'SELECT * FROM `module_instances` WHERE `id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rules_flow_id){
            try {
                $sql = "SELECT `rfl`.`location` FROM (`rules_flow_locations` `rfl` " .
                            "LEFT JOIN `rules_flows` `rf` ON `rf`.`current_rule_id` = `rfl`.`rule_id`) " .
                            "WHERE `rf`.`id` = ? AND `rfl`.`module_instance_id` = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(1, (int)$rules_flow_id, PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$id, PDO::PARAM_INT);
                if ($stmt->execute()){
                    $location = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($location){
                        $info = array_merge($info, $location);
                    }
                }
            } catch (Exception $e){
                // 'v1' does not have `rules_flow_locations` table. 
                // if error occuered, ignore.
                #Util::syslog("In " . __CLASS__ . "::" . __METHOD__ . " error occuered. " . $e->getMessage());
                throw $e;
            }
        }
        if (!empty($info)){
            $instance = $this->getInstance($id);
            $moduleInfo = (new ModuleModel())->getModuleInfo($instance['module_id']);
            $volume = $this->getVolumes($id, $moduleInfo);
            if (!empty($volume)){
                $info['volumes'] = $volume;
            }
        }
        return $info;
    }

    /**
     * get srcs
     *
     * @param obj
     *
     */
    public function getSrcs($id)
    {
        $sql = 'SELECT * FROM `module_instance_sources` WHERE `module_instance_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * get dsts
     *
     * @param obj
     *
     */
    public function getDsts($id)
    {
        $sql = 'SELECT * FROM `module_instance_destinations` WHERE `module_instance_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * get srcs
     *
     * @param obj
     *
     */
    public function getParams($id)
    {
        $sql = 'SELECT * FROM `module_instance_params` WHERE `module_instance_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVolumes($id, $moduleInfo = null)
    {
        $sql = 'SELECT * FROM `module_instance_volumes` WHERE `module_instance_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        $this->volumes = [];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $v){
            $hash = ['name' => $v['name'], 'hostPath' => ['path' => $v['host_path']]];
            if (!empty($v['type']))
                $hash['hostPath']['type'] = $v['type'];
            $this->volumes[] = $hash;
        }
        if ($moduleInfo != null){
            $volumes = $this->volumes;
            // If mountPath is not found in module instance, add hostPath by same name.
            foreach ($moduleInfo['modules']['volumeMounts'] ?? [] as $volumeMount){
                $found = false;
                foreach ($volumes as $volume){
                    if ($found = ($volume['name'] == $volumeMount['name'])){
                        break;
                    }
                }
                if (!$found){
                    $volumeMount['hostPath']['path'] = $volumeMount['mountPath'];
                    unset($volumeMount['mountPath']);
                    $volumes[] = $volumeMount;
                }
            }
            return $volumes;
        }
        return $this->volumes;
    }

    /**
     * get next record
     *
     * @param
     *
     */
     /*
    public function getNext($id)
    {
        $pos = $this->get($id);
        if (!pos) {
            return false;
        }
        $sql = 'SELECT * FROM `module_instances` WHERE `flow_id` = :flow_id AND `seq` = :seq';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':flow_id', $flow_id, PDO::PARAM_INT);
        $stmt->bindValue(':seq', $seq, PDO::PARAM_INT);
        $flow_id = $pos['flow_id'];
        $seq = $pos['seq'] + 1;
        $ret = $stmt->fetch();
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
    */

    /**
     * get Instance URL
     *
     * @param flow_id
     * @param name
     * @param svc
     *
     * @return url
     *
     */
    public function getInstanceUrl($flow_id, $name, $svc = false)
    {
        $sql = 'SELECT * FROM `module_instances` WHERE `flow_id` = :flow_id AND `name` = :name';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':flow_id', $flow_id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name);
        #$ret = $stmt->fetch();
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        $url = '';
        $moduleModel = new ModuleModel();
        $module = $moduleModel->get($res['module_id']);
        if ($svc) {
            if ($module['container_port'] == '443') {
                $url = 'https://' . $res['url'];
            } else {
                $url = $res['url'];
            }
            return $url;
        }
        return $this->k8s->getPodIp($res['id']);
    }
    public function getClusterUrl($flow_id, $name)
    {
        return $this->k8s->getClusterUrl($this->getIdByFlowIdAndName($flow_id, $name));
    }
    public function getEndpointUrl($flow_id, $name)
    {
        return $this->k8s->getEndpointUrl($this->getIdByFlowIdAndName($flow_id, $name));
    }

    public function getConfigData($flowId, $id, $flow)
    {
        $instance = $this->get($id);
        $params = $this->getParams($id);
        $json = array();

        $json['id'] =  $flow['name'] . '/' . $instance['name'];
        if ($instance['debug']) {
            $json['debug'] = 'on';
        } else {
            $json['debug'] = 'off';
        }
        // assign source
        foreach ($this->getSrcs($id) as $src){
            $json['source'][] = $src['name'];
        }
        // assign destination
        foreach ($this->getDsts($id) as $dst){
            $json['destination'][] = $dst['name'];
        }
        // assign params
        foreach ($params as $param) {
            $json['params'][$param['key']] = $this->replaceUrl(json_decode($param['value'], true), $flowId);
        }
        return json_encode($json);
    }
    protected function replaceUrl($value, $flowId)
    {
        $values = is_array($value) ? $value : [ 0 => $value ];
        array_walk($values, function(&$v,$k) use($flowId) {
            if (is_array($v)){
                #Util::syslog("replaceUrl array begin = " . json_encode($v));
                $v = $this->replaceUrl($v, $flowId);
                #Util::syslog("replaceUrl array end = " . json_encode($v));
            } else if (preg_match('/{(.+)}/', $v, $matches)) {
                #Util::syslog("replaceUrl str begin = " . $v);
                $url = $this->getClusterUrl($flowId, $matches[1]);
                if (!$url){
                    $url = $this->getEndpointUrl($flowId, $matches[1]);
                }
                if (!$url){
                    $url = $this->getInstanceUrl($flowId, $matches[1]);
                }
                if ($url){
                    #Util::syslog("replaceUrl str match = " . $matches[1]);
                    $v = str_replace('{'.$matches[1].'}', $url, $v);
                }
                #Util::syslog("replaceUrl str end = " . $v);
            }
        });
        return is_array($value) ? $values : $values[0];
    }

    /**
     * get id by instance_name
     *
     */
    public function getIdByName($flowName, $name)
    {
        $flow = new FlowModel();
        $flow_id = $flow->getIdByName($flowName);
        if (empty($flow_id)) {
            return false;
        }
        return $this->getIdByFlowIdAndName($flow_id, $name);
    }
    public function getIdByFlowIdAndName($flow_id, $name)
    {
        $sql = 'SELECT * FROM `module_instances` WHERE `flow_id` = :flow_id AND (`name` = :name OR `uuid` = :name)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':flow_id', $flow_id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        return $stmt->fetch(PDO::FETCH_ASSOC)['id'];
    }
    public function getIdByUuid($uuid)
    {
        $sql = 'SELECT * FROM `module_instances` WHERE `uuid` = :uuid';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uuid', $uuid);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        return $stmt->fetch(PDO::FETCH_ASSOC)['id'];
    }

    /**
     * update pod info
     * 
     */
    public function updatePodInfo($instanceId)
    {
        $instance = $this->get($instanceId);
        if (!$instance) {
            return false;
        }
        // url
        $url = $this->k8s->getPodUrl($instanceId);
        // location
        $location = $this->k8s->getPodLocation($instanceId);

        $sql = 'UPDATE `module_instances` SET `url` = :url, `location` = :location, `updated_at` = :updated_at WHERE `id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':url', $url);
        $stmt->bindValue(':location', $location);
        $stmt->bindValue(':updated_at', Util::getTimeStamp());
        $stmt->bindValue(':id', $instanceId, PDO::PARAM_INT);
        return $stmt->execute();
    }
    public function updateUuid($instanceId, $uuid)
    {
        $instance = $this->get($instanceId);
        if (!$instance) {
            return false;
        }
        $instance_name = static::createInstanceName($instance['name'], $uuid);

        $sql = 'UPDATE `module_instances` SET `uuid` = :uuid, `instance_name` = :instance_name, `updated_at` = :updated_at WHERE `id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uuid', $uuid);
        $stmt->bindValue(':instance_name', $instance_name);
        $stmt->bindValue(':updated_at', Util::getTimeStamp());
        $stmt->bindValue(':id', $instanceId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getInstance($id)
    {
        $sql = 'SELECT * FROM `module_instances` WHERE `id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * get instance Info
     *
     */
    public function getInstanceInfo($id)
    {
        $instanceInfo = array();
        $srcs = array();
        $dsts = array();
        $params = array();
        // instance
        // XXX JOIN module table
        $instance = $this->getInstance($id);
        // name
        $instanceInfo['name'] = $instance['name'];
        // isntance name
        $instanceInfo['instance_name'] = $instance['instance_name'];
        // module
        $module = new ModuleModel();
        $moduleInfo = $module->getModuleInfo($instance['module_id']);
        $instanceInfo['module'] = $moduleInfo['metadata']['name'];
        // XXX get pod info
        $instanceInfo['location'] = $instance['location'];
        // XXX get pod info
        if (isset($instance['url'])){
            $instanceInfo['url'] = $instance['url'];
        }
        // uuid
        $instanceInfo['uuid'] = $instance['uuid'];
        // type
        $instanceInfo['type'] = ($instance['seq'] == 1) ? 'endpoint' : 'component';
        // debug
        if ($instance['debug'] == 1) {
            $instanceInfo['debug'] = 'on';
        } else {
            $instanceInfo['debug'] = 'off';
        }
        // creation Timestamp
        $instanceInfo['creationTimestamp'] = date(DATE_ISO8601, strtotime($instance['created_at']));
        // lastModifiedTimestamp
        $instanceInfo['lastModifiedTimestamp'] = date(DATE_ISO8601, strtotime($instance['updated_at']));
        // srcs
        $sql = 'SELECT * FROM `module_instance_sources` WHERE `module_instance_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        $instanceSrcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($instanceSrcs as $instanceSrc) {
            $srcs[] = $instanceSrc['name'];
        }
        if (!empty($srcs)) {
            $instanceInfo['config']['source'] = $srcs;
        }
        // dsts
        $sql = 'SELECT * FROM `module_instance_destinations` WHERE `module_instance_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        $instanceDsts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($instanceDsts as $instanceDst) {
            $dsts[] = $instanceDst['name'];
        }
        if (!empty($dsts)) {
            $instanceInfo['config']['destination'] = $dsts;
        }
        // params
        $sql = 'SELECT * FROM `module_instance_params` WHERE `module_instance_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        $instanceParams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($instanceParams as $instanceParam) {
            $params[$instanceParam['key']] = $instanceParam['value'];
        }
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $params[$key] = json_decode($value, true);
            }
            $instanceInfo['config']['params'] = $params;
        }
        // volumes
        $instanceInfo['volumes'] = $this->getVolumes($id, $moduleInfo);
        return $instanceInfo;
    }

    /**
     * get all instance info
     *
     *
     */
    public function getAllInstanceInfo($flow_id)
    {
        $instanceInfos = array();
        $sql = 'SELECT * FROM `module_instances` WHERE `flow_id` = :flow_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':flow_id', $flow_id);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($instances as $instance) {
            $instanceInfos[] = $this->getInstanceInfo($instance['id']);
        }
        return $instanceInfos;
    }

    /**
     * get all instance
     *
     *
     */
    public function getAllInstance($flow_id)
    {
        $sql = 'SELECT * FROM `module_instances` WHERE `flow_id` = :flow_id ORDER BY `seq` ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':flow_id', $flow_id);
        if (!$stmt->execute()){
            assert(false);
            return false;
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function postConfig($flow_id, $flow, $instance)
    {
        // create post config
        $config = $this->getConfigData($flow_id, $instance['id'], $flow);
        // endpoint(podIP:containerPort/config)
        $url = $this->k8s->getEndpointUrl($instance['id']);
        if (!$url){
            Util::syslog("fail to get endpoint url, instance id = " . $instance['id']);
            return null;
        }
        $url .= '/config';
        // post config
        $cmd = "curl -sk -o /dev/null -w '%{HTTP_CODE}' -X POST -d '" . $config . "' " . $url;
        // exec curl
        Util::syslog('post begin. ' . $config);
        $ret = exec($cmd) == '200';
        Util::syslog('post ' . ($ret ? 'success' : 'failed') . '.');
        return $ret;
    }
}

