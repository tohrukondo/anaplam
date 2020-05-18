<?php

namespace Anaplam\Model;

use PDO;
use Anaplam\Model;
use Anaplam\Utility as Util;
use Anaplam\ResourceAlreadyExistException;
use Anaplam\RequiredParamException;
use Anaplam\InvalidParamException;
use Anaplam\LengthExceedException;

class ModuleModel extends Model
{
    protected static $name = 'module';

    /**
     * protected module
     *
     * - name
     * - description
     * - image
     * - container_port
     * - uuid for update
     * - created_at for update
     *
     * @var array
     */
    public $module = array();

    /**
     * protected labels
     *
     * - key
     * - value
     *
     * @var array
     */
    public $labels = array();

    /**
     * protected deny src
     *     
     * - order
     * - deny_src
     *
     * @var array
     */
    public $deny_srcs = array();

    /**
     * protected deny dst
     *
     * - order
     * - deny_dsts
     *
     * @var array
     */
    public $deny_dsts = array();

    public $volumeMounts = [];

    /**
     * construct
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * set module info
     *
     * @param $moduleInfo
     */
    public function setModuleInfo($moduleInfo)
    {
        // module
        if (isset($moduleInfo['metadata']['name'])) {
            $this->module['name'] = $moduleInfo['metadata']['name'];
        }
        if (isset($moduleInfo['metadata']['description'])) {
            $this->module['description'] = $moduleInfo['metadata']['description'];
        }
        if (isset($moduleInfo['modules']['image'])) {
            $this->module['image'] = $moduleInfo['modules']['image'];
        }
        if (isset($moduleInfo['modules']['containerPort'])) {
            $this->module['container_port'] = $moduleInfo['modules']['containerPort'];
        }
        // label
        if (isset($moduleInfo['metadata']['labels'])) {
            if (is_array($moduleInfo['metadata']['labels'])) {
                foreach ($moduleInfo['metadata']['labels'] as $key => $value) {
                    $this->labels[$key] = $value;
                }
            }
        }
        // deny src
        if (isset($moduleInfo['modules']['denySource'])) {
            if (is_array($moduleInfo['modules']['denySource'])) {
                foreach ($moduleInfo['modules']['denySource'] as $key => $value) {
                    $this->deny_srcs[$key] = $value;
                }
            }
        }
        // deny dst 
        if (isset($moduleInfo['modules']['denyDestination'])) {
            if (is_array($moduleInfo['modules']['denyDestination'])) {
                foreach ($moduleInfo['modules']['denyDestination'] as $key => $value) {
                    $this->deny_dsts[$key] = $value;
                }
            }
        }
        // volumeMounts
        $this->volumeMounts = $moduleInfo['modules']['volumeMounts'] ?? null;
    }

    /**
     * valid
     *
     * @param update
     *
     */
    public function valid($update = false)
    {
        Util::kubernetesLabelCheck(static::$name, 'name', $this->module['name'] ?? null);

        // name duplicate
        if (!$update && $this->getIdByName($this->module['name'])){
            throw new ResourceAlreadyExistException(static::$name, 'name', $this->module['name']);
        }
        // image
        if (empty($this->module['image'])) {
            throw new RequiredParamException(static::$name, 'image');
        }
        // container_port
        $containerPort = $this->module['container_port'] ?? '';
        if (empty($containerPort)) {
            throw new RequiredParamException(static::$name, 'containerPort');
        }
        if (preg_match('/^\D*$/', $containerPort, $matches)) {
            throw new InvalidParamException(static::$name, 'containerPort', $containerPort);
        }
        if ((int)($containerPort) < 0 || 65535 < (int)($containerPort)) {
            throw new InvalidParamException(static::$name, 'containerPort', $containerPort);
        }
        foreach ($this->volumeMounts ?? [] as $vm){
            Util::kubernetesLabelCheck(static::$name, 'volume mount name', $vm['name']);
        }
    }

    /**
     * create
     *
     * @param $update
     *
     */
    public function create($update = false)
    {
        $id = false;
        $timeStamp = Util::getTimeStamp(); 
        $this->db->beginTransaction();
        // module
        $id = $this->__createModule($id, $timeStamp, $update);
        if (!$id) {
            $this->db->rollBack();
            return false;
        }
        // label
        if (!$this->__createLables($id, $timeStamp)) {
            $this->db->rollBack();
            return false;
        }
        // deny srcs
        if (!$this->__createDenySrcs($id, $timeStamp)) {
            $this->db->rollBack();
            return false;
        }
        // deny dsts
        if (!$this->__createDenyDsts($id, $timeStamp)) {
            $this->db->rollBack();
            return false;
        }
        // volumeMounts
        if (!$this->__createVolumeMounts($id, $timeStamp)) {
            $this->db->rollBack();
            return false;
        }
        $this->db->commit();
        return $id;
    }

    /**
     * create module
     *
     * @param $id
     * @param $timeStamp
     * @param $update
     *
     */
    private function __createModule($id, $timeStamp, $update)
    {
        $sql = 'INSERT INTO `modules` (`name`, `description`, `uuid`, `image`, `container_port`, `created_at`, `updated_at`) VALUES (:name, :description, :uuid, :image, :container_port, :created_at, :updated_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmt->bindParam(':image', $image, PDO::PARAM_STR);
        $stmt->bindParam(':container_port', $container_port, PDO::PARAM_INT);
        $stmt->bindParam(':created_at', $created_at, PDO::PARAM_STR);
        $stmt->bindParam(':updated_at', $updated_at, PDO::PARAM_STR);
        // set param
        if (isset($this->module['name'])) {
            $name = $this->module['name'];
        }
        if (isset($this->module['description'])) {
            $description = $this->module['description'];
        } else {
            $description = null;
        }
        if ($update) {
            $uuid = $this->module['uuid'];
        } else {
            $uuid = Util::createUUID();
        }
        if (isset($this->module['image'])) {
            $image = $this->module['image'];
        }
        if (isset($this->module['container_port'])) {
            $container_port = $this->module['container_port'];
        }
        if ($update) {
            $created_at = $this->module['created_at'];
        } else {
            $created_at = $timeStamp;
        }
        $updated_at = $timeStamp;
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {       
            return false;
        }
        return $this->db->lastInsertId();
    }

    /**
     * create lables
     *
     */
    private function __createLables($id, $timeStamp)
    {
        if (empty($this->labels)) {
            return true;
        }
        $sql = 'INSERT INTO `module_labels` (`module_id`, `key`, `value`, `created_at`, `updated_at`) VALUES (:module_id, :key, :value, :created_at, :updated_at)';
        foreach($this->labels as $key => $value) {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':module_id', $id, PDO::PARAM_INT);
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
     * create deny source
     *
     */
    private function __createDenySrcs($id, $timeStamp)
    {
        if (empty($this->deny_srcs)) {
            return true;
        }
        $sql = 'INSERT INTO `module_deny_sources` (`module_id`, `module`, `created_at`, `updated_at`) VALUES (:module_id, :module, :created_at, :updated_at)';
        foreach($this->deny_srcs as $key => $module) {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':module_id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':module', $module, PDO::PARAM_STR);
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
     * create deny dst
     *
     */
    private function __createDenyDsts($id, $timeStamp)
    {
        if (empty($this->deny_dsts)) {
            return true;
        }
        $sql = 'INSERT INTO `module_deny_destinations` (`module_id`, `module`, `created_at`, `updated_at`) VALUES (:module_id, :module, :created_at, :updated_at)';
        foreach($this->deny_dsts as $key => $module) {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':module_id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':module', $module, PDO::PARAM_STR);
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
     * create volumeMounts
     *
     */
    private function __createVolumeMounts($id, $timeStamp)
    {
        if (empty($this->volumeMounts)) {
            return true;
        }
        $sql = 'INSERT INTO `module_volume_mounts` (`module_id`, `name`, `mount_path`, `created_at`, `updated_at`) VALUES (:module_id, :name, :mount_path, :created_at, :updated_at)';
        foreach($this->volumeMounts as $hash) {
            if (empty($hash['name']) || empty($hash['mountPath'])){
                continue;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':module_id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $hash['name'], PDO::PARAM_STR);
            $stmt->bindParam(':mount_path', $hash['mountPath'], PDO::PARAM_STR);
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
     * delete
     *
     * @param $id
     *  
     */
    public function delete($id)
    {
        $this->db->beginTransaction();
        try {
            // module
            $sql = 'DELETE FROM `modules` WHERE `id` = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            // label
            $sql = 'DELETE FROM `module_labels` WHERE `module_id` = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            // deny srcs
            $sql = 'DELETE FROM `module_deny_sources` WHERE `module_id` = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            // deny dsts
            $sql = 'DELETE FROM `module_deny_destinations` WHERE `module_id` = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            // volumeMounts
            $sql = 'DELETE FROM `module_volume_mounts` WHERE `module_id` = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->db->commit();
    }

    /**
     * update
     *
     * @param id
     *
     */
    public function update($id)
    {
        $newId = false;
        $oldModule = $this->get($id);
        if (!$oldModule) {
            return false;
        }
        // set old param
        $this->module['uuid'] = $oldModule['uuid'];
        $this->module['created_at'] = $oldModule['created_at'];
        // delete
        $this->delete($id);
        // create
        return $this->create(true);
    }

    /**
     * get
     *
     * @param id
     *
     */
    public function get($id)
    {
        $sql = "SELECT * FROM `modules` WHERE `id` = :id";
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
        if (!empty($res)){
            $volumeMounts = $this->getVolumeMounts($id);
            if (!empty($volumeMounts)){
                $res['volumeMounts'] = $volumeMounts;
            }
        }
        return $res;
    }

    /**
     * get id by name
     *
     */
    public function getIdByName($name)
    {
        $sql = "SELECT * FROM `modules` WHERE `name` = :name";
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
        $sql = "SELECT * FROM `modules` WHERE `uuid` = :uuid";
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
     * get labels
     *
     * @param  $id
     * @return $labels
     */
    public function getLabels($id)
    {
        $labels = array();

        $sql = "SELECT * FROM `module_labels` WHERE `module_id` = :module_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':module_id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $moduleLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($moduleLabels as $moduleLabel) {
            $labels[$moduleLabel['key']] = $moduleLabel['value'];
        }
        return $labels;
    }

    /**
     * get deny src
     *
     * @param  $id
     * @return $denySrcs
     */
    public function getDenySrcs($id)
    {
        $denySrcs = array();

        $sql = "SELECT * FROM `module_deny_sources` WHERE `module_id` = :module_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':module_id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }

        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($modules as $module) {
            $denySrcs[] = $module['module'];
        }
        return $denySrcs;
    }

    /**
     * get deny dst
     *
     * @param  $id
     * @return $denyDsts
     */
    public function getDenyDsts($id)
    {
        $denyDsts = array();

        $sql = "SELECT * FROM `module_deny_destinations` WHERE `module_id` = :module_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':module_id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($modules as $module) {
            $denyDsts[] = $module['module'];
        }
        return $denyDsts;
    }

    /**
     * get volume mounts
     *
     * @param  $id
     * @return $denyDsts
     */
    public function getVolumeMounts($id)
    {
        $denyDsts = array();

        $sql = "SELECT * FROM `module_volume_mounts` WHERE `module_id` = :module_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':module_id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $volumeMounts = [];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $volumeMount) {
            $volumeMounts[] = ['name' => $volumeMount['name'], 'mountPath' => $volumeMount['mount_path']];
        }
        return $volumeMounts;
    }

    /**
     * get module Info
     *
     */
    public function getModuleInfo($id)
    {
        $moduleInfo = array();
        $labels = array();
        $denySrcs = array();
        $denyDsts = array();
        // module
        $sql = 'SELECT * FROM `modules` WHERE `id` = :id';
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
        $module = $stmt->fetch(PDO::FETCH_ASSOC);
        $moduleInfo['metadata']['name'] = $module['name'];
        if (!empty($module['description'])) {
            $moduleInfo['metadata']['description'] = $module['description'];
        }
        $moduleInfo['modules']['image'] = $module['image'];
        $moduleInfo['modules']['containerPort'] = (int)$module['container_port'];
        // labels
        $sql = "SELECT * FROM `module_labels` WHERE `module_id` = :module_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':module_id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $moduleLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($moduleLabels as $moduleLabel) {
            $labels[$moduleLabel['key']] = $moduleLabel['value'];
        }
        if (!empty($labels)) {
            $moduleInfo['metadata']['labels'] = $labels;
        }
        // uuid set
        $moduleInfo['metadata']['uuid'] = $module['uuid'];
        // creation Timestamp
        $moduleInfo['metadata']['creationTimestamp'] = date(DATE_ISO8601, strtotime($module['created_at']));
        // lastModifiedTimestamp
        $moduleInfo['metadata']['lastModifiedTimestamp'] = date(DATE_ISO8601, strtotime($module['updated_at']));
        // deny srcs
        $sql = "SELECT * FROM `module_deny_sources` WHERE `module_id` = :module_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':module_id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $moduleDenySources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($moduleDenySources as $moduleDenySource) {
            $denySrcs[] = $moduleDenySource['module'];
        }
        if (!empty($denySrcs)) {
            $moduleInfo['modules']['denySource'] = $denySrcs;
        }
        // deny dsts
        $sql = "SELECT * FROM `module_deny_destinations` WHERE `module_id` = :module_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':module_id', $id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        $moduleDenyDsts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($moduleDenyDsts as $moduleDenyDst) {
            $denyDsts[] = $moduleDenyDst['module'];
        }
        if (!empty($denyDsts)) {
            $moduleInfo['modules']['denyDestination'] = $denyDsts;
        }
        // volumeMounts
        $moduleInfo['modules']['volumeMounts'] = $this->getVolumeMounts($id) ?? null;
        return $moduleInfo;
    }

    /**
     * get all module info
     *
     *
     */
    public function getAllModuleInfo()
    {
        $moduleInfos = array();
        $sql = 'SELECT * FROM `modules`';
        $modules = $this->db->query($sql);
        foreach($modules as $module) {
            $moduleInfos[] = $this->getModuleInfo($module['id']);
        }
        return $moduleInfos;
    }
}

