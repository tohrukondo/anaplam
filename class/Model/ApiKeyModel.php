<?php

namespace Anaplam\Model;

use PDO;
use Anaplam\Model;
use Anaplam\Utility as Util;

class ApiKeyModel extends Model
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
     * @param api_key
     * @return bool
     */
    public function create($api_key, $user = NULL, $user_id = NULL)
    {
        $timeStamp = Util::getTimeStamp();
        $sql = 'INSERT INTO `api_keys` (`api_key`, `user`, `user_id`, `created_at`, `updated_at`) VALUES (:api_key, :user, :user_id, :created_at, :updated_at)';

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':api_key', $api_key, PDO::PARAM_STR);
            $stmt->bindParam(':user', $user, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $stmt->bindValue(':created_at', $timeStamp, PDO::PARAM_STR);
            $stmt->bindParam(':updated_at', $timeStamp, PDO::PARAM_STR);
            if (!$stmt->execute()){
                assert(false);
                throw new \RuntimeException("ApiKey database error.");
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
        return true;
    }

    /**
     * delete
     *
     * @param api_key
     * @return bool
     */
    public function delete($api_key)
    {
        $this->db->beginTransaction();
        try {
            $sql = 'DELETE FROM `api_keys` WHERE `api_key` = :api_key';
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':api_key', $api_key, PDO::PARAM_STR);
            $stmt->execute();
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
        return true;
    }

    /**
     * get all
     *
     */
    public function getAll()
    {
        $sql = "SELECT * FROM `api_keys`";
        $apiKeys = $this->db->query($sql);
        return $apiKeys;
    }

    /**
     * exist
     *
     * @param $api_key
     * @param bool
     */
    public function exist($api_key)
    {
        $sql = 'SELECT * FROM `api_keys` WHERE `api_key` = :api_key';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':api_key', $api_key, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /**
     * user_key
     *
     * @param $api_key
     * @param bool
     */
    public function user_key($user_id)
    {
        $sql = 'SELECT `api_key`, MAX(`updated_at`) FROM `api_keys` WHERE `user_id` = :user_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        try {
            $ret = $stmt->execute();
            if (!$ret) {
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

