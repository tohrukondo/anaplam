<?php

namespace Anaplam\Model;

use PDO;
use RuntimeException;
use Anaplam\Model;
use Anaplam\Utility as Util;
use Anaplam\RequiredParamException;
use Anaplam\ResourceAlreadyExistException;
use Anaplam\ResourceDuplicateException;

use Maclof\Kubernetes\Models\Pod;
use Maclof\Kubernetes\Models\Service;

class FlowV2Model extends FlowModel
{
    protected $rules_flow_id;
    protected static $name = 'flow';

    /**
     * construct
     *
     */
    public function __construct($rules_flow_id)
    {
        parent::__construct();
        $this->rules_flow_id = $rules_flow_id;
    }

    protected static function createModuleInstanceModel()
    {
        return new ModuleInstanceV2Model();
    }

    /**
     * valid
     *
     * @param update
     *
     */
    public function valid($update = false)
    {
        // do nothingu.
    }
    protected function _valid($update = false)
    {
        Util::kubernetesLabelCheck(static::$name, 'name', $this->flow['name']);

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

            // XXX module instance deny src check.
/*
            // module instance dst seq check.
            $instanceInfo = $instance->getInfoInstance();
            $module = $instance->getModule();
            $moduleName = $module['name'];
            $dsts = $instance->getInfoDsts();

            // instance self deny dst check
            foreach ($dsts as $dst) {
                $denyDsts = $instance->getModuleDenyDsts();
                foreach ($checkInstances as $checkPos => $checkInstance) {
                    $checkInstanceInfo = $checkInstance->getInfoInstance();
                    if ($checkInstance->isName($dst)) {
                        $checkModule = $checkInstance->getModule();
                        foreach ($denyDsts as $denyDst) {
                            if ($denyDst == $checkModule['name']) {
                                $msg = $instanceInfo['name'] . '(module ' . $instanceInfo['module'] . ') destination to ' . $checkInstanceInfo['name'] . '(module ' . $checkInstanceInfo['module'] . ')  is deny by self.';
                                throw new RuntimeException($msg);
                            }
                        }
                    }
                }
            }

            // dst instance deny src check
            foreach ($dsts as $dst) {
                foreach ($checkInstances as $checkPos => $checkInstance) {
                    $checkInstanceInfo = $checkInstance->getInfoInstance();
                    if ($checkPos < $pos) {
                        continue;
                    }
                    if ($checkInstance->isName($dst)) {
                        $denySrcs = $checkInstance->getModuleDenySrcs();
                        foreach ($denySrcs as $denySrc) {
                            if ($denySrc == $moduleName) {
                                $msg = $instanceInfo['name'] . '(module ' . $instanceInfo['module'] .   ') destination to is deny by ' . $checkInstanceInfo['name'] . '(module ' . $checkInstanceInfo['module'] . ') src deny.';
                                throw new RuntimeException($msg);
                            }
                        }
                    }
                }
            }
*/
        }
    }

    /**
     * create
     *
     */
    public function create()
    {
        $timeStamp = Util::getTimeStamp();
        $this->db->beginTransaction();
        try {
            $this->_valid(false);
            $id = $this->createFlow($timeStamp);
            if (!$this->createLabels($id, $timeStamp)){
                throw new RuntimeException("flow's label can not be created for database error.");
            }
            $this->createInstances($id, $timeStamp);
            $this->db->commit();
        } catch (Exception $e){
            $this->db->rollBack();
            throw $e;
        }
        return $id;
    }
    protected function createFlow($timeStamp)
    {
        if (!isset($this->flow['name'])) {
            throw new RequiredParamException(static::$name, 'name');
        }
        $sql = 'INSERT INTO `flows` (`rules_flow_id`, `name`, `description`, `uuid`, `created_at`, `updated_at`) VALUES ' .
                                   '(:rules_flow_id,  :name,  :description,  :uuid,  :created_at,  :updated_at)';
        $uuid = Util::createUUID();
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':rules_flow_id', $this->rules_flow_id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $this->flow['name']);
        $stmt->bindValue(':description', $this->flow['description'] ?? null);
        $stmt->bindValue(':uuid', $uuid);
        $stmt->bindValue(':created_at', $timeStamp);
        $stmt->bindValue(':updated_at', $timeStamp);
        if (!$stmt->execute()){
            throw new RuntimeException("flow can not be created for database error.");
        }
        // set flow info
        $this->flow['uuid'] = $uuid;
        return $this->db->lastInsertId();
    }
    protected function createInstances($id, $timeStamp)
    {
        // check topology.
        $this->topologyCheck();
        // sort by topology.
        $orders = $this->topologicalSortInstances();
        if (is_null($orders)){
            throw new RuntimeException("module instance topological error.");
        }
        // create instances.
        foreach ($this->instances as $instance) {
            $seqId = array_search($instance->getInfoInstance()['name'], $orders);
            assert(!is_null($seqId));
            if (!$instance->create($id, $timeStamp, $seqId + 1)) {
                throw new RuntimeException();
            }
        }
    }
    /**
     * update
     *
     * @param $id
     * @param $updateFlowInfo
     * @param bool
     */
     /*
    public function update($id, $updateFlowInfo)
    {
    }
    */
    public function delete($id, $db_only = true)
    {
        /* delete only database datas. */
        parent::delete($id, $db_only);
    }
    public function getFlowInfo($id)
    {
        $info = parent::getFlowInfo($id);
        $names = array_column($info['flow'], 'name');

        // apply the current rule's module instance location.
        $rules_flow = (new RulesFlow())->_restore($this->rules_flow_id);
        if ($rules_flow->current_rule_id){
            $rules = RulesFlowLocation::find_by_params(['rule_id' => $rules_flow->current_rule_id]);
            foreach ($rules as $rule){
                $instance = (static::createModuleInstanceModel())->get($rule['module_instance_id']);
                $flow_index = array_search($instance['name'], $names);
                if ($flow_index !== false){
                    $flow = &$info['flow'][$flow_index];
                    #Util::syslog('apply rule\'s location. ' . $flow['name'] . ':' . $flow['location'] . ' -> ' . $rule['location']);
                    $flow['location'] = $rule['location'];
                } else {
                    #Util::syslog($instance['name'] . ' is not found in ' . json_encode($names));
                }
            }
        }
        return $info;
    }

    public function getIdByName($name)
    {
        $sql = 'SELECT * FROM `flows` WHERE `name` = ? AND `rules_flow_id` = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $name);
        $stmt->bindValue(2, $this->rules_flow_id, PDO::PARAM_INT);
        if (!$stmt->execute()){
            throw new RuntimeException(); // TODO:
        }
        return $stmt->fetch(PDO::FETCH_ASSOC)['id'];
    }

    /**
     * get all flow info
     *
     *
     */
    public function getAllFlowInfo()
    {
        $sql = 'SELECT * FROM `flows` WHERE `rules_flow_id` = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $this->rules_flow_id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        foreach($stmt as $flow) {
            $flowInfo = $this->getFlowInfo($flow['id']);
            foreach ($flowInfo['metadata'] as $k => $v){
                $data[$k] = $v;
            }
            $data['flow'] = $flowInfo['flow'];
            $flowInfos[] = $data;
        }
        return $flowInfos ?? [];
    }
    public function updateActivation($id)
    {
        // get current flow's module instances.
        $rfinfo = (new RulesFlowModel())->get($this->rules_flow_id);
        $finfo = $this->getFlowInfo($id);
        $names = array_column($finfo['flow'], 'name');

        $uuids = [];
        $moduleInstanceModel = static::createModuleInstanceModel();
        $instances = $moduleInstanceModel->getAllInstance($id);
        $reverse_instances = array_reverse($instances);
        foreach ($reverse_instances as $instance){
            $flow_index = array_search($instance['name'], $names);
            #Util::syslog('name = ' . $instance['name']);
            if ($flow_index !== false && !empty($finfo['flow'][$flow_index]['location'])){
                #Util::syslog('change location. ' . $instance['location'] . ' -> ' . $finfo['flow'][$flow_index]['location']);
                $instance['location'] = $finfo['flow'][$flow_index]['location'];
            } else {
                #Util::syslog('location org = ' . ($instance['location'] ?? ''));
                #Util::syslog('location finfo. ' . ($finfo['flow'][$flow_index]['location'] ?? ''));
            }
            $podInfo = $this->getPodInstance($rfinfo, $instance);
            if ($podInfo){
                $uuid = $podInfo['pod']['metadata']['labels']['uuid'];
                if (strcmp($instance['uuid'], $uuid) == 0){
                    if ($this->isSameLocation($podInfo, $instance)){
                        $uuids[] = $uuid;
                        Util::syslog("reuse " . $uuid);
                        $instanceInfo = $moduleInstanceModel->get($instance['id']);
                        $this->updateSystemLabels($podInfo, $instanceInfo, $rfinfo, $finfo);
                        continue;
                    } else {
                        // If pod is on the same flow but can not be reuse,
                        // it is necessary to update the uuid of module instance.
                        $uuid = Util::createUUID();
                        $moduleInstanceModel->updateUuid($instance['id'], $uuid);
                        Util::syslog("module instance update uuid " . $uuid);
                    }
                }
            }
            // instantiation.
            $newInstance = $moduleInstanceModel->getInstance($instance['id']);
            $moduleInstanceModel->activateModule($rfinfo, $finfo, $newInstance);
            $uuids[] = $newInstance['uuid'];
        }
        $this->postConfig($id);

        // delete unnecessary pods and services.
        $name = $rfinfo['metadata']['name'];
        $this->deleteUnuse(true, $name, $uuids);
        $this->deleteUnuse(false, $name, $uuids);
    }

    private function deleteUnuse($svc, $rules_flow_name, $uuids)
    {
        #Util::syslog("deleteUnuse(". $svc . ", " . $rules_flow_name . ", " . json_encode($uuids));

        if ($svc){
            $repo = $this->k8s->client->services();
            $delete = 'deleteSvc';
        } else {
            $repo = $this->k8s->client->pods();
            $delete = 'deletePod';
        }
        $rm_uuids = [];
        $models = $repo->setLabelSelector(['rules_flow' => $rules_flow_name])->find();
        foreach ($models->all() as $model){
            $info = json_decode($model->getSchema(), true);
            #Util::syslog("podinfo = ". json_encode($info));
            $uuid = $info['metadata']['labels']['uuid'] ?? null;
            if ($uuid && !in_array($uuid, $uuids, true)){
                $rm_uuids[$uuid] = $info['metadata']['labels']['seq'] ?? 0;
            }
        }
        asort($rm_uuids);
        foreach ($rm_uuids as $uuid => $seq){
            $this->k8s->$delete($uuid);
        }
    }

    protected function getPodInstance($rfinfo, $instance)
    {
        $module = (new ModuleModel())->get($instance['module_id']);
        $selector = ['rules_flow' => $rfinfo['metadata']['name'], 'module' => $module['name']];
        $podInfo = $this->k8s->getPodInfoBySelector($selector);
        if (!($podInfo['pod'] ?? null) || !($podInfo['svc'] ?? null)){
            /*
            Util::syslog("pod or svc is not found. selector => " . json_encode($selector));
            Util::syslog("name = " . $instance['name']);
            Util::syslog("instance name = " . $instance['instance_name']);
            Util::syslog("location = " . $instance['location']);
            Util::syslog("podInfo = " . json_encode($podInfo));
            */
            return null;
        }
        return $podInfo;
    }
    protected function isSameLocation($podInfo, $instance)
    {
        return ($podInfo['pod']['spec']['nodeSelector']['location'] ?? null) == ($instance['location'] ?? null);
    }
    protected function updateSystemLabels($podInfo, $instanceInfo, $rfinfo, $finfo)
    {
        $plabels1 = $podInfo['pod']['metadata']['labels'] ?? [];
        $slabels1 = $podInfo['svc']['metadata']['labels'] ?? [];

        $plabels2 = $this->k8s->preparePodInfo($instanceInfo, $rfinfo, $finfo['metadata'])['pod']['metadata']['labels'] ?? [];
        $slabels2 = $this->k8s->prepareSvcInfo($instanceInfo, $rfinfo, $finfo['metadata'])['pod']['metadata']['labels'] ?? [];

        $update = false;
        static $keys = ['rules_flow', 'flow', 'module', 'uuid', 'seq'];
        foreach ($keys as $key){
            if (!array_key_exists($key, $plabels1) || !array_key_exists($key, $plabels2)){
                $update = true;
                break;
            }
            if ($plabels1[$key] != $plabels2[$key]){
                $update = true;
                break;
            }
            if (!array_key_exists($key, $slabels1) || !array_key_exists($key, $slabels2)){
                $update = true;
                break;
            }
            if ($slabels1[$key] != $slabels2[$key]){
                $update = true;
                break;
            }
        }
        $key = 'type';
        if (!array_key_exists($key, $plabels1) || !array_key_exists($key, $plabels2)){
            $update = true;
        } else if ($plabels1[$key] != $plabels2[$key]){
            $update = true;
        }
        if ($update){
            #Util::syslog("update system pod labels. " . $podInfo['pod']['metadata']['name'] . ": " . json_encode($plabels1) . " -> " . json_encode($plabels2));
            #Util::syslog("update system svc labels. " . $podInfo['svc']['metadata']['name'] . ": " . json_encode($slabels1) . " -> " . json_encode($slabels2));
            $this->k8s->updateLabels($podInfo['pod']['metadata']['name'], $plabels2, $podInfo['svc']['metadata']['name'], $slabels2);
        }
    }
    public function deactivate($id)
    {
        (static::createModuleInstanceModel())->deactivateModule($id);
    }
    public function postConfig($id)
    {
        $moduleInstanceModel = static::createModuleInstanceModel();
        $instances = $moduleInstanceModel->getAllInstance($id);
        $reverse_instances = array_reverse($instances);
        $finfo = $this->get($id);
        foreach ($reverse_instances as $instance){
            $moduleInstanceModel->postConfig($id, $finfo, $instance);
        }
    }
    protected function topologicalSortInstances()
    {
        $moduleModel = new ModuleModel();
        foreach ($this->instances as $instance){
            // create name-dsts map.
            $name = $instance->getInfoInstance()['name'];
            $dsts = $instance->getInfoDsts();
            $graph[$name] = $dsts;
        }
        return self::topologicalSort([], $graph);
    }
    public static function topologicalSort($orders, $graph)
    {
        if (empty($graph)){
            return $orders;
        }
        $keys = array_keys($graph);
        foreach ($graph as $k => $v){
            $keys = array_diff($keys, $v);
            if (empty($keys)){
                return null;    // loop exist.
            }
        }
        foreach ($keys as $key){
            unset($graph[$key]);
        }
        if (empty($orders) && count($keys) > 1){
            $msg = "can not determine the flow's endpoint by the `destination` of module instances. " . 
                "the candidates are follows.(" . json_encode($keys) . " )";
            throw new RuntimeException($msg);
        }
        return self::topologicalSort(array_merge($orders, $keys), $graph);
    }
}

