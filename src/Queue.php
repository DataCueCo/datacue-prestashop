<?php

namespace DataCue\PrestaShop;

class Queue
{
    public static function addJob($action, $model, $modelId, $job)
    {
        $action = \Db::getInstance()->escape($action);
        $model = \Db::getInstance()->escape($model);
        $modelId = \Db::getInstance()->escape("$modelId");
        $job = \Db::getInstance()->escape(json_encode($job), true);

        return \Db::getInstance()->execute("
			INSERT INTO `" . _DB_PREFIX_ . "datacue_queue` (`action`, `model`, `model_id`, `job`, `status`, `created_at`) 
			VALUES ('$action', '$model', $modelId, '$job', 0, NOW())");
    }

    public static function addJobWithoutModelId($action, $model, $job)
    {
        $action = \Db::getInstance()->escape($action);
        $model = \Db::getInstance()->escape($model);
        $job = \Db::getInstance()->escape(json_encode($job), true);

        return \Db::getInstance()->execute("
			INSERT INTO `" . _DB_PREFIX_ . "datacue_queue` (`action`, `model`, `job`, `status`, `created_at`) 
			VALUES ('$action', '$model', '$job', 0, NOW())");
    }

    public static function updateJob($id, $job)
    {
        $id = \Db::getInstance()->escape("$id");
        $job = \Db::getInstance()->escape(json_encode($job), true);

        return \Db::getInstance()->execute("
            UPDATE `" . _DB_PREFIX_ . "datacue_queue` SET `job` = '$job' WHERE `id_datacue_queue` = $id
        ");
    }

    public static function isJobExisting($action, $model, $modelId)
    {
        $action = \Db::getInstance()->escape($action);
        $model = \Db::getInstance()->escape($model);
        $modelId = \Db::getInstance()->escape("$modelId");

        return \Db::getInstance()->getValue("
            SELECT 1 FROM `" . _DB_PREFIX_ . "datacue_queue`
            WHERE `action` = '$action' AND `model` = '$model' AND `model_id` = $modelId");
    }

    public static function isActionExisting($action)
    {
        $action = \Db::getInstance()->escape($action);

        return \Db::getInstance()->getValue("
            SELECT 1 FROM `" . _DB_PREFIX_ . "datacue_queue` WHERE `action` = '$action'");
    }

    public static function getAliveJob($action, $model, $modelId)
    {
        $action = \Db::getInstance()->escape($action);
        $model = \Db::getInstance()->escape($model);
        $modelId = \Db::getInstance()->escape("$modelId");

        $job = \Db::getInstance()->getRow("
            SELECT * FROM `" . _DB_PREFIX_ . "datacue_queue`
            WHERE `action` = '$action' AND `model` = '$model' AND `model_id` = $modelId AND `status` = 0");
        if ($job) {
            $job['job'] = json_decode($job['job']);
        }
        return $job;
    }

    public static function getNextAliveJob()
    {
        $job = \Db::getInstance()->getRow("
            SELECT * FROM `" . _DB_PREFIX_ . "datacue_queue` WHERE `status` = 0");
        if ($job) {
            $job['job'] = json_decode($job['job']);
        }
        return $job;
    }

    public static function updateJobStatus($id, $status)
    {
        $id = \Db::getInstance()->escape("$id");
        $status = \Db::getInstance()->escape("$status");

        return \Db::getInstance()->execute("
            UPDATE `" . _DB_PREFIX_ . "datacue_queue` SET `status` = $status, `executed_at` = NOW() WHERE `id_datacue_queue` = $id
        ");
    }
}
