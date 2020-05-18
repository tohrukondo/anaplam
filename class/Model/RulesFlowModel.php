<?php

namespace Anaplam\Model;

use PDO;
use InvalidArgumentException;
use Anaplam\Model;
use Anaplam\Model\FlowV2Model;
use Anaplam\Model\RulesModel;
use Anaplam\Model\EnvsModel;
use Anaplam\Utility as Util;
use Anaplam\ResourceNotFoundException;

use Maclof\Kubernetes\Models\Pod;
use Maclof\Kubernetes\Models\Service;

class RulesFlowModel extends Model
{
    static $name = 'rule flow';

    public function __construct()
    {
        parent::__construct();
    }
    public function get($id, $flow_id = null)
    {
        $rules_flow = new RulesFlow();
        $info = $rules_flow->get($id);

        // labels
        $sql = 'SELECT * FROM `rules_flow_labels` WHERE `rules_flow_id` = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        if (!$stmt->execute()) {
            return false;
        }
        $flowLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($flowLabels as $flowLabel) {
            $labels[$flowLabel['key']] = $flowLabel['value'];
        }
        if (!empty($labels)) {
            $info['metadata']['labels'] = $labels;
        }
        // rules
        $info['rules'] = (new RulesModel())->get($id, $rules_flow->current_rule_id);
        // flows
        if ($flow_id == null){
            $info['flows'] = (new FlowV2Model($id))->getAllFlowInfo();
        } else {
            $flowInfo = (new FlowV2Model($id))->getFlowInfo($flow_id);
            foreach ($flowInfo['metadata'] as $k => $v){
                $data[$k] = $v;
            }
            $data['flow'] = $flowInfo['flow'];
            $info['flows'][] = $data;
        }
        // env
        $info['env'] = (new EnvsModel())->get($id);

        return $this->adjustRules($id, $info);
    }
    public function create($rulesFlowInfo)
    {
        // rules_flow
        $id = null;
        $this->db->beginTransaction();
        try {
            $rules_flow = new RulesFlow();
            if (!$rules_flow->create($rulesFlowInfo)){
                throw new \LogicException("rules flow database error.");
            }
            $id = $rules_flow['id'];
            $this->innerCreate($id, $rules_flow, $rulesFlowInfo);
            $this->db->commit();
        } catch (Exception $e){
            $this->db->rollBack();
            throw $e;
        }
        $this->createEndpoint($id);
        return $id;
    }
    public function delete($id)
    {
        $this->db->beginTransaction();
        try {
            $rules_flow = new RulesFlow();
            if (!$rules_flow->_restore($id)){
                throw new ResourceNotFoundException("rule flow", "id = " . $id);
            }
            // delete endpoint.
            $this->k8s->deleteSvc($rules_flow['uuid']);
            // current rule's flow id.
            $rule = (new Rule())->_restore($rules_flow['current_rule_id'] ?? 0);
            if (!$rule){
                // If no rule exists and only one flow exists, this flow has instanciated.
                $flowModel = new FlowV2Model($id);
                $flows = $flowModel->getAllFlowInfo();
                if (count($flows) == 1){
                    $rule['flow_id'] = $flowModel->getIdByName($flows[0]['name']);
                }
            }
            $current_flow_id = $rule['flow_id'] ?? 0;
            if ($current_flow_id){
                (new FlowV2Model($id))->deactivate($current_flow_id);
            }
            // delete rules
            (new RulesModel())->delete($id);
            // delete env
            Env::delete_by_params(['rules_flow_id' => $id]);
            // delete flows
            $this->deleteFlows($id);
            // delete rules_flow
            (new RulesFlow())->_delete($id);
            $this->db->commit();
        } catch (Exception $e){
            $this->db->rollBack();
            throw $e;
        }
    }
    public function list()
    {
        return $this->getAll();
    }
    public function update($rulesFlowId, $rulesFlowInfo)
    {
        // rules_flow
        $id = null;
        $this->db->beginTransaction();
        try {
            $rules_flow = (new RulesFlow())->update($rulesFlowInfo);
            if (!$rules_flow){
                throw new \LogicException("rules flow database error.");
            }
            $id = $rules_flow['id'];
            if ($id != $rulesFlowId){
                throw new \RuntimeException("The passed rule flow names do not match. id = " . $id . ",  rulesFlowId = " . $rulesFlowId);
            }
            $this->deleteFlows($id);
            (new RulesModel())->delete($id);
            Env::delete_by_params(['rules_flow_id' => $id]);

            $this->innerCreate($id, $rules_flow, $rulesFlowInfo);
            $this->db->commit();
        } catch (Exception $e){
            $this->db->rollBack();
            throw $e;
        }
        return $id;
    }
    public function getRules($id)
    {
        // rules
        $rules_flow = new RulesFlow();
        $info = $rules_flow->get($id);
        $info['rules'] = (new RulesModel())->get($id, $rules_flow->current_rule_id);
        // env
        $info['env'] = (new EnvsModel())->get($id);
        return $this->adjustRules($id, $info);
    }
    protected function adjustRules($id, $info)
    {
        if (empty($info['rules'])){
            $flows = $info['flows'];
            switch (count($flows)){
            case 0:
                $flows = (new FlowV2Model($id))->getAllFlowInfo();
                if (count($flows) != 1){
                    break;
                }
                // fall through.
            case 1:
                $info['rules'][] = ['name' => 'current', 'flow' => $flows[0]['name']];
                break;
            }
        }
        return $info;
    }
    public function modify($id, $rulesFlowInfo)
    {
        $this->db->beginTransaction();
        try {
            (new EnvsModel())->update($id, $rulesFlowInfo['env']);
            $this->evaluateRules($id, $rulesFlowInfo['env']);
            $this->db->commit();
        } catch (Exception $e){
            $this->db->rollBack();
            throw $e;
        }
    }


    protected function innerCreate($id, $rules_flow, $rulesFlowInfo)
    {
        // name
        Util::kubernetesLabelCheck(static::$name, 'name', $rules_flow->name);

        // labels
        $labels = $rulesFlowInfo['metadata']['labels'] ?? null;
        if ($labels){
            $this->createLabels($id, $labels, $rules_flow->updated_at);
        }
        // flows↲
        foreach ($rulesFlowInfo['flows'] as $flow){
            #Util::syslog("flow => " . json_encode($flow));
            $data['metadata']['name'] = $flow['name'];
            $data['metadata']['description'] = $flow['description'] ?? null;
            $data['metadata']['labels'] = $flow['labels'] ?? [];
            $data['flow'] = $flow['flow'];
            $flow_model = new FlowV2Model($id);
            $flow_model->setFlowInfo($data);
            $flow_model->create();
        }
        // Confirm the container_port and protocol of all endpoint module instances are the same.
        $target = $this->checkEndpointTarget($id);
        $rules_flow->target_port = $target['target_port'];
        $rules_flow->protocol = $target['protocol'];
        $rules_flow->_update();
        // rules
        $rules_flow->current_rule_id = null;
        $rules_flow->_update();
        (new RulesModel())->create($id, $rulesFlowInfo['rules'] ?? []);
        // env
        (new EnvsModel())->create($id, $rulesFlowInfo['env'] ?? []);
        // activate according rules.
        $this->evaluateRules($id, $rulesFlowInfo['env'] ?? []);
    }
    protected function deleteFlows($id)
    {
        $stmt = $this->db->prepare("SELECT `id` FROM `flows` WHERE `rules_flow_id` = ?");
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        if (!$stmt->execute()){
            throw new \BadFunctionCallException("");    // TODO: 
        }
        $flow_model =  new FlowV2Model($id);
        foreach ($stmt as $flowInfo){
            $flow_model->delete($flowInfo['id']);
        }
    }
    protected function checkEndpointTarget($id)
    {
        $sql = "SELECT DISTINCT `m`.`container_port`, `m`.`protocol` " .
            "FROM (`module_instances` `mi` " .
                "JOIN `modules` `m` ON `mi`.`module_id` = `m`.`id`) " .
                "JOIN `flows` `f` ON `mi`.`flow_id` = `f`.`id` " .
            "WHERE `f`.`rules_flow_id` = ? " .
                "AND `mi`.`seq` = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $id);
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count == 1){
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['target_port' => $res['container_port'], 'protocol' => $res['protocol']];
        }
        if ($count > 1){
            $msg = "Endpoints in the rule flow(`" . $rules_flow->name . "`) have different container ports or protocols.";
            $msg = $msg . " => " . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $msg = "There is something wrong with the endpoints in the rule flow(`" . $rules_flow->name . "`).";
        }
        throw new \RuntimeException($msg);
    }

    protected function createEndpoint($id)
    {
        $rfinfo = $this->get($id);
        $this->k8s->createEndpointSvc($rfinfo);
    }

    protected function evaluateRules($id, $env)
    {
        $rules_flow = new RulesFlow();
        if (!$rules_flow->_restore($id)){
            throw new ResourceNotFoundException("rule flow", "id = " . $id);
        }
        $flow_model = new FlowV2Model($id);
        $rules_model = new RulesModel();

        // current rule's flow id.
        $rule = (new Rule())->_restore($rules_flow['current_rule_id'] ?? 0);
        $old_flow_id = $rule['flow_id'] ?? 0;
        $new_flow_id = 0;
        $new_flow_name = '';

        $rule = $rules_model->evaluateCondition($id, $env);
        if ($rule){
            $new_flow_id = $rule['flow_id'];
            $new_flow_name = $rule['name'];
        } else {
            // If there is no match rule and the flow is unique, activate it.
            $flowInfo = $flow_model->getAllFlowInfo();
            if (count($flowInfo) == 1){
                $new_flow_name = $flowInfo[0]['name'];
                $new_flow_id = $flow_model->getIdByName($new_flow_name);
            } else {
                throw new \RuntimeException("all the rules did not match. so the flow could not be determined.");
            }
        }
        #Util::syslog("env is " . json_encode($env));
        Util::syslog("new flow is '" . $new_flow_name . "'");
        if ($new_flow_id){
            // set 'current_rule_id' on database.
            $rules_flow['current_rule_id'] = $rule['id'] ?? 0;
            $rules_flow->_update();
            // update active flow.
            $flow_model->updateActivation($new_flow_id);
        } else if ($old_flow_id){
            $flow_model->deactivate($old_flow_id);
        }
    }
    /**
     * create label
     *
     */
    private function createLabels($id, $labels, $timeStamp)
    {
        if (empty($labels)) {
            return true;
        }
        $sql = "INSERT INTO `rules_flow_labels` (`rules_flow_id`, `key`, `value`, `created_at`, `updated_at`)" .
                                        "VALUES (:rules_flow_id,  :key,  :value,  :created_at,  :updated_at)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':rules_flow_id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->bindValue(':created_at', $timeStamp);
        $stmt->bindValue(':updated_at', $timeStamp);
        foreach ((array)$labels as $key => $value){
            if (!$stmt->execute()){
                throw new \BadFunctionCallException("");
            }
        }
        return true;
    }
    /**
     * get id by name
     *
     */
    public function getIdByName($name)
    {
        $rules_flows = (new RulesFlow())->find_by_params(['name' => $name]);
        return $rules_flows[0]['id'] ?? null;
    }

    /**
     * get id by uuid
     *
     */
    public function getIdByUuid($uuid)
    {
        $rules_flows = (new RulesFlow())->find_by_params(['uuid' => $uuid]);
        return $rules_flows[0]['id'] ?? null;
    }

    /**
     * get all flow info
     *
     *
     */
    public function getAll()
    {
        $rules_flows = $this->db->query('SELECT * FROM `rules_flows`');
        $infos = [];
        foreach($rules_flows as $rules_flow) {
            $infos[] = $this->get($rules_flow['id']);
        }
        return $infos;
    }
}

