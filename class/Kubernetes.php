<?php

namespace Anaplam;

use Maclof\Kubernetes\Client;
use Maclof\Kubernetes\Models\Pod;
use Maclof\Kubernetes\Models\Service;

use Anaplam\Utility as Util;

use Anaplam\Model\ModuleModel;
use Anaplam\Model\ModuleInstanceModel;
use Anaplam\Model\RulesFlowModel;
use Anaplam\Model\FlowModel;
use Anaplam\ResourceAlreadyExistException;
use Anaplam\ResourceNotFoundException;
use Anaplam\PodStatusException;

class Kubernetes
{
    /**
     * protected client
     *
     * @var 
     */
    public $client;

    protected static $pod_init_wait_count = 12;#12;
    protected static $pod_term_wait_count = 5;#5;
    protected static $http_timeout = 30;#30;

    /**
     * construct
     *
     */
    public function __construct()
    {
        $this->client = new Client([
            'master' => Util::getEnv('API_URL'),
            'token' => Util::getEnv('TOKEN'),
            'ca_cert' => Util::getEnv('CA_CERT'),
            'client_cert' => Util::getEnv('CLIENT_CERT'),
            'client_key' => Util::getEnv('CLIENT_KEY'),
            'timeout' => static::$http_timeout,
            'verify' => false,
        ]);
    }

    /**
     * get nodes
     *
     */
    public function getNodes()
    {
        return $this->client->nodes()->find();
    }

    /**
     * get node
     *
     * @param $location
     *
     */
    public function getNode($location)
    {
        return $this->client->nodes()->setLabelSelector(['location' => $location])->first();
    }

    public function getPodInfo($instanceId)
    {
        $instanceInfo = (new ModuleInstanceModel())->get($instanceId);
        return $this->getPodInfoByUuid($instanceInfo['uuid']);
    }
    public function getPodInfoByUuid($uuid)
    {
        return $this->getPodInfoBySelector(['uuid' => $uuid]);
    }
    public function getPodInfoBySelector($selector)
    {
        $pod['pod'] = $this->client->pods()->setLabelSelector($selector)->first();
        $pod['svc'] = $this->client->services()->setLabelSelector($selector)->first();
        $pod['ep'] = $this->client->endpoints()->setLabelSelector($selector)->first();
        return array_map(function($m){ return $m ? json_decode($m->getSchema(), true) : null; }, $pod);
    }
    /**
     * get pod info
     *
     */
     /*
    public function getPodInfo($instanceId)
    {
        $podInfo = array();
        $instance = new ModuleInstanceModel();
        $instanceInfo = $instance->get($instanceId);
        $pod = $this->client->pods()->setLabelSelector(['uuid' => $instanceInfo['uuid']])->first();
        if (empty($pod)) {
            return $podInfo;
        }
        $pod = json_decode($pod->getSchema(), true);
        $svc = $this->client->services()->setLabelSelector(['uuid' => $instanceInfo['uuid']])->first(); 
        $svc = json_decode($svc->getSchema(), true);
        $podInfo['pod'] = $pod;
        $podInfo['svc'] = $svc;
        return $podInfo;
    }
    */

    /**
     * get pod info status
     *
     */
    public function getPodStatus($instanceId)
    {
        $podInfo = $this->getPodInfo($instanceId);
        return $podInfo['pod']['status']['phase'];
    }

    /**
     * get pod url
     *
     */
    public function getPodUrl($instanceId)
    {
        $podInfo = $this->getPodInfo($instanceId);
        $ip = $podInfo['pod']['status']['hostIP'] ?? null;
        $port = $podInfo['svc']['spec']['ports']['0']['nodePort'] ?? null;
        return static::getUrl($ip, $port);
    }
    public function getClusterUrl($instanceId)
    {
        $podInfo = $this->getPodInfo($instanceId);
        $ip = $podInfo['svc']['spec']['clusterIP'];
        $port = $podInfo['svc']['spec']['ports']['0']['port'];
        return static::getUrl($ip, $port);
    }
    public function getEndpointUrl($instanceId)
    {
        $podInfo = $this->getPodInfo($instanceId);
        if (isset($podInfo['ep']['subsets'])){
            $ip = $podInfo['ep']['subsets']['addresses'][0]['ip'] ?? null;
            $port = $podInfo['ep']['subsets']['ports'][0]['port'] ?? null;
        }
        if (empty($ip)){
            $ip = $podInfo['pod']['status']['podIP'] ?? null;
        }
        if (empty($port)){
            $port = $podInfo['pod']['spec']['containers'][0]['ports'][0]['containerPort'] ?? null;
        }
        return static::getUrl($ip, $port);
    }
    protected static function getUrl($ip, $port)
    {
        if (!is_null($ip) && !is_null($port)){
            $url = empty($port) ? $ip : $ip.':'.$port;
            return ($port == 443) ? "https://" . $url . "/" : $url;
        }
        return null;
    }

    /**
     * get pod info location
     *
     */
    public function getPodLocation($instanceId)
    {
        $podInfo = $this->getPodInfo($instanceId);
        return $podInfo['pod']['spec']['nodeSelector']['location'] ?? null;
    }

    /**
     * get pod ip
     *
     */
    public function getPodIp($instanceId)
    {
        $podInfo = $this->getPodInfo($instanceId);
        return $podInfo['pod']['status']['podIP'];
    }

    /**
     * create instance
     *
     * param @instanceId
     * param @flowInfo
     * param @flowLabels
     */
    public function createInstance($instanceId, $rfinfo, $flowInfo)
    {
        $rules_flow_id = null;
        if ($rfinfo){
            $rules_flow_id = (new RulesFlowModel())->getIdByName($rfinfo['name']);
        }
        $instance = new ModuleInstanceModel();
        $instanceInfo = $instance->get($instanceId, $rules_flow_id);
        #Util::syslog("Kubernetes::createInstance() instanceInfo = " . json_encode($instanceInfo));
        // create pod
        if (!$this->createPod($instanceInfo, $rfinfo, $flowInfo)) {
            return false;
        }
        // create service
        if (!$this->createSvc($instanceInfo, $rfinfo, $flowInfo)) {
            return false;
        }
        // update pod info
        for ($i = 0; $i < static::$pod_init_wait_count; ++$i) {
            $status = $this->getPodStatus($instanceId);
            if ($status == 'Running') {
                $wait_count = false;
            } else {
                sleep(5);
            }
        }
        if (!$instance->updatePodInfo($instanceId)) {
            return false;
        }
        if ($status != 'Running') {
            throw new PodStatusException($status);
        }
        return true;
    }

    /**
     * create pod
     *
     * param @instanceInfo
     * param @podInfo
     * param @moduleLabels
     */
    private function createPod($instanceInfo, $rfinfo, $flowInfo)
    {
        $podInfo = $this->preparePodInfo($instanceInfo, $rfinfo, $flowInfo);
        #Util::syslog("Creating pod... info = " . json_encode($podInfo));
        $pod = new Pod($podInfo);
        if ($this->client->pods()->exists($pod->getMetadata('name'))) {
            throw new ResourceAlreadyExistException('pod', 'name', $pod->getMetadata('name'));
        }
        Util::syslog("create pod begin. info = " . json_encode($podInfo));
        try {
            $result = $this->client->pods()->create($pod);
        } catch (\GuzzleHttp\Exception\ClientException $e){
Util::syslog($e->getResponse()->getBody()->getContents());
            throw $e;
        }
        Util::syslog("create pod end.");
        return $result;
    }
    public function updatePod($oldInstanceInfo, $instanceInfo, $rfinfo, $flowInfo)
    {
        $oldPodInfo = $this->preparePodInfo($oldInstanceInfo, $rfinfo, $flowInfo);
        $name = $oldPodInfo['metadata']['name'] ?? null;
        $podInfo = $this->preparePodInfo($instanceInfo, $rfinfo, $flowInfo);
        $pod = new UpdatePod($name, $podInfo);
        if (!$this->client->pods()->exists($pod->getMetadata('name'))) {
            throw new ResourceNotFoundException('pod', 'name', $pod->getMetadata('name'));
        }
        Util::syslog("update pod begin. info = " . json_encode($podInfo));
        $result = $this->client->pods()->update($pod);
        Util::syslog("update pod end.");
        return $result;
    }
    public function preparePodInfo($instanceInfo, $rfinfo, $flowInfo)
    {
        $moduleInfo = (new ModuleModel())->get($instanceInfo['module_id']);
        $podInfo['metadata']['name'] = mb_strtolower($instanceInfo['instance_name']);
        $labels = (new ModuleModel())->getLabels($instanceInfo['module_id']);
        if (isset($rfinfo['name'])){
            $labels['rules_flow'] = $rfinfo['name'];
        }
        if (!array_key_exists('name', $flowInfo)){
            throw new \InvalidArgumentException(json_encode($flowInfo));
        }
        $labels['flow'] = $flowInfo['name'];
        $labels['module'] = $moduleInfo['name'];
        $labels['uuid'] = $instanceInfo['uuid'];
        $labels['seq'] = $instanceInfo['seq'];
        $labels['type'] = $instanceInfo['seq'] == 1 ? 'endpoint' : 'component';
        $podInfo['metadata']['labels'] = $labels;

        $container['name'] = $moduleInfo['name'];
        $container['image'] = $moduleInfo['image'];
        $container['ports'][0]['containerPort'] = (int)$moduleInfo['container_port'];
        foreach ($moduleInfo['volumeMounts'] as $vm){
            if (!empty($vm['name'])){
                $container['volumeMounts'][] = $vm;
            }
        }
        $containers[] = $container;
        $podInfo['spec']['containers'] = $containers;
        foreach ($instanceInfo['volumes'] as $v){
            if (!empty($v['name'])){
                $podInfo['spec']['volumes'][] = $v;
            }
        }
        if (isset($instanceInfo['location'])) {
            $podInfo['spec']['nodeSelector']['location'] = $instanceInfo['location'];
        }
        // do not auto restart, for avoiding restart with invalid parameters.
        $podInfo['spec']['restartPolicy'] = 'Never';
        return $podInfo;
    }

    /**
     * create svc
     *
     * param @podInfo
     */
    private function createSvc($instanceInfo, $rfinfo, $flowInfo)
    {
        $svcInfo = $this->prepareSvcInfo($instanceInfo, $rfinfo, $flowInfo);
        $svc = new Service($svcInfo);
        if ($this->client->services()->exists($svc->getMetadata('name'))) {
            throw new ResourceAlreadyExistException('service', 'name', $svc->getMetadata('name'));
        }
        return $this->client->services()->create($svc);
    }
    public function updateSvc($oldInstanceInfo, $instanceInfo, $rfinfo, $flowInfo)
    {
        $name = $this->prepareSvcInfo($oldInstanceInfo, $rfinfo, $flowInfo)['metadata']['name'] ?? null;
        $svc = new UpdateService($name, $this->prepareSvcInfo($instanceInfo, $rfinfo, $flowInfo));
        if (!$this->client->services()->exists($name)) {
            return false;
        }
        return $this->client->services()->update($svc);
    }
    public function prepareSvcInfo($instanceInfo, $rfinfo, $flowInfo)
    {
        $moduleInfo = (new ModuleModel())->get($instanceInfo['module_id']);
        $svcInfo['metadata']['name'] = mb_strtolower($instanceInfo['instance_name']);
        $labels = $flowInfo['labels'] ?? [];
        if (isset($rfinfo['name'])){
            $labels['rules_flow'] = $rfinfo['name'];
        }
        if (!array_key_exists('name', $flowInfo)){
            throw new \InvalidArgumentException(json_encode($flowInfo));
        }
        $labels['flow'] = $flowInfo['name'];
        $labels['module'] = $moduleInfo['name'];
        $labels['uuid'] = $instanceInfo['uuid'];
        $labels['seq'] = $instanceInfo['seq'];
        $svcInfo['metadata']['labels'] = $labels;

        // v1: 'NodePort', v2: ClusterIP
        $spec['type'] = $rfinfo ? 'ClusterIP' : 'NodePort';
        $ports['port'] = (int)$moduleInfo['container_port'];
        $ports['targetPort'] = (int)$moduleInfo['container_port'];
        $ports['protocol'] = $moduleInfo['protocol'] ?? 'TCP';
        $spec['ports'][] = $ports;
        $spec['selector']['uuid'] = $instanceInfo['uuid'];
        $svcInfo['spec'] = $spec;
        return $svcInfo;
    }

    public function createEndpointSvc($rfinfo)
    {
        $svcInfo['metadata']['name'] = mb_strtolower($rfinfo['metadata']['name']);
        $svcInfo['metadata']['labels'] = $rfinfo['labels'] ?? [];
        $svcInfo['metadata']['labels']['uuid'] = $rfinfo['metadata']['uuid'];

        $svcInfo['spec']['type'] = 'NodePort';

        if (isset($rfinfo['metadata']['nodePort'])){
            $ports['nodePort'] = $rfinfo['metadata']['nodePort'];
        }
        $ports['port'] = $ports['targetPort'] = $rfinfo['metadata']['targetPort'];
        $ports['protocol'] = $rfinfo['metadata']['protocol'];
        $svcInfo['spec']['ports'][] = $ports;

        $selectors['rules_flow'] = $rfinfo['metadata']['name'];
        $selectors['type'] = 'endpoint';
        $svcInfo['spec']['selector'] = $selectors;

        $svc = new Service($svcInfo);
        if ($this->client->services()->exists($svc->getMetadata('name'))) {
            return false;
        }
        return $this->client->services()->create($svc);
    }

    public function updateLabels($podName, $podLabels, $svcName, $svcLabels)
    {
        $names = [ 'pod' => $podName, 'svc' => $svcName ];
        $labels = [ 'pod' => $podLabels, 'svc' => $svcLabels ];
        $results = [];
        foreach (['pod', 'svc'] as $res){
            $cmd = 'kubectl label ' . $res;
            $cmd .= ' ' . $names[$res];
            foreach ($labels[$res] as $key => $value){
                $cmd .= ' '. $key . '=' . $value;
            }
            $cmd .= ' --overwrite';
            #Util::syslog($cmd);
            $results[$res] = exec($cmd);
        }
        return $results;
    }

    /**
     * delete instance
     *
     * param @uuid 
     */
    public function deleteInstance($uuid)
    {
        // svc
        if (!$this->deleteSvc($uuid)) {
            return false;
        }
        // pod
        if (!$this->deletePod($uuid)) {
            return false;
        }
        return true;
    }
    public function deleteInstances($labelSelector)
    {
        $this->deleteSvcs($labelSelector);
        $this->deletePods($labelSelector);
    }

    /**
     * delete svc
     *
     * param @uuid
     */
    public function deleteSvc($uuid)
    {
        $svc = $this->client->services()->setLabelSelector(['uuid' => $uuid])->first();
        if (empty($svc)) {
            return true;
        }
        return $this->client->services()->delete($svc);
    }
    private function deleteSvcs($labelSelector)
    {
        $svcs = $this->client->services()->setLabelSelector($labelSelector)->find();
        foreach ($svcs as $svc){
            $svc_repo->delete($svc);
        }
    }

    /**
     * delete pod
     *
     * param @uuid
     */
    public function deletePod($uuid)
    {
        for ($i = 0; $i < static::$pod_term_wait_count; ++$i) {
            $pod = $this->client->pods()->setLabelSelector(['uuid' => $uuid])->first();
            if (is_null($pod)) {
                return true;
            }
            if (!$this->client->pods()->delete($pod)){
                return false;
            }
            sleep(5);
        }
    }
    private function deletePods($labelSelector)
    {
        for ($i = 0; $i < static::$pod_term_wait_count; ++$i) {
            $pods = $this->client->pods()->setLabelSelector($labelSelector)->find();
            if (empty($pods)){
                return;
            }
            foreach ($pods as $pod){
                $pod_repo->delete($pod);
            }
            sleep(5);
        }
    }
}

class UpdatePod extends Pod
{
    protected $name;

    public function __construct($name, $attributes)
    {
        parent::__construct($attributes);
        $this->name = $name;
    }
    public function getSchema()
    {
        $info = json_decode(parent::getSchema(), true);
        $info['kind'] = 'Pod';
        return json_encode($info, JSON_PRETTY_PRINT);
    }
    public function getMetadata($key)
    {
        #Util::syslog("call UpdatePod::getMetadata(" . $key . ") return " . $this->name);
        return (strcmp($key, 'name') == 0) ? $this->name : parent::getMetadata($key);
    }
}
class UpdateService extends Service
{
    protected $name;

    public function __construct($name, $attributes)
    {
        parent::__construct($attributes);
        $this->name = $name;
    }
    public function getSchema()
    {
        $info = json_decode(parent::getSchema());
        $info['kind'] = 'Service';
        return json_encode($info, JSON_PRETTY_PRINT);
    }
    public function getMetadata($key)
    {
        return (strcmp($key, 'name') == 0) ? $this->name : parent::getMetadata($key);
    }
}

