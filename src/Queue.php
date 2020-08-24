<?php
/**
 * MIT License
 * Copyright (c) 2019 DataCue
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 *  @author    DataCue <contact@datacue.co>
 *  @copyright 2019 DataCue
 *  @license   https://opensource.org/licenses/MIT MIT License
 */

namespace DataCue\PrestaShop;

class Queue
{
    /**
     * Task status after initial
     */
    const STATUS_NONE = 0;

    /**
     * Task status for pending
     */
    const STATUS_PENDING = 1;

    /**
     * Task status for success
     */
    const STATUS_SUCCESS = 2;

    /**
     * Task status for failure
     */
    const STATUS_FAILURE = 3;

    public static function addJob($action, $model, $modelId, $job)
    {
        $action = \Db::getInstance()->escape($action);
        $model = \Db::getInstance()->escape($model);
        $modelId = \Db::getInstance()->escape("$modelId");
        $job = \Db::getInstance()->escape(json_encode($job), true);

        return \Db::getInstance()->execute("
            INSERT INTO `" . _DB_PREFIX_ . "datacue_queue`
            (`action`, `model`, `model_id`, `job`, `status`, `created_at`)
            VALUES ('$action', '$model', $modelId, '$job', 0, NOW())
            ON DUPLICATE KEY UPDATE `job`='$job', `status`=0, `created_at`=NOW()");
    }

    public static function addJobWithoutModelId($action, $model, $job)
    {
        $action = \Db::getInstance()->escape($action);
        $model = \Db::getInstance()->escape($model);
        $job = \Db::getInstance()->escape(json_encode($job), true);

        return \Db::getInstance()->execute("
            INSERT INTO `" . _DB_PREFIX_ . "datacue_queue`
            (`action`, `model`, `job`, `status`, `created_at`)
            VALUES ('$action', '$model', '$job', 0, NOW())
            ON DUPLICATE KEY UPDATE `job`='$job', `status`=0, `created_at`=NOW()");
    }

    public static function updateJob($id, $job)
    {
        $id = \Db::getInstance()->escape("$id");
        $job = \Db::getInstance()->escape(json_encode($job), true);

        return \Db::getInstance()->execute("
            UPDATE `" . _DB_PREFIX_ . "datacue_queue` SET `job` = '$job'
            WHERE `id_datacue_queue` = $id
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
            WHERE `action` = '$action' AND `model` = '$model' AND
            `model_id` = $modelId AND `status` = 0");
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

    public static function getNextAliveJobByAction($action)
    {
        $action = \Db::getInstance()->escape($action);

        $job = \Db::getInstance()->getRow("
            SELECT * FROM `" . _DB_PREFIX_ . "datacue_queue`
            WHERE `action` = '$action' AND `status` = 0");
        if ($job) {
            $job['job'] = json_decode($job['job']);
        }
        return $job;
    }

    public static function getAliveJobsByModelAndAction($model, $action, $limit = 200)
    {
        $model = \Db::getInstance()->escape($model);
        $action = \Db::getInstance()->escape($action);

        $res = \Db::getInstance()->query("
            SELECT * FROM `" . _DB_PREFIX_ . "datacue_queue`
            WHERE `model` = '$model' AND `action` = '$action' AND `status` = 0
            LIMIT $limit");
        $items = iterator_to_array($res);

        foreach ($items as &$item) {
            $item['job'] = json_decode($item['job']);
        }

        return $items;
    }

    public static function getAllByAction($action)
    {
        $action = \Db::getInstance()->escape($action);

        $res = \Db::getInstance()->query("
            SELECT * FROM `" . _DB_PREFIX_ . "datacue_queue`
            WHERE `action` = '$action'");
        $items = iterator_to_array($res);

        foreach ($items as &$item) {
            $item['job'] = json_decode($item['job']);
        }
        return $items;
    }

    public static function getQueueStatus()
    {
        $res = \Db::getInstance()->query("
            SELECT model,status,count(1) as total
            FROM `" . _DB_PREFIX_ . "datacue_queue`
            WHERE `model` != 'init' group by 1,2");
        $items = iterator_to_array($res);

        return $items;
    }

    public static function updateJobStatus($id, $status)
    {
        $id = \Db::getInstance()->escape("$id");
        $status = \Db::getInstance()->escape("$status");

        return \Db::getInstance()->execute("
            UPDATE `" . _DB_PREFIX_ . "datacue_queue` SET `status` = $status,
            `executed_at` = NOW()
            WHERE `id_datacue_queue` = $id"
        );
    }

    public static function updateMultiJobsStatus(array $ids, $status)
    {
        $status = \Db::getInstance()->escape("$status");
        $idsStr = implode(',', $ids);

        return \Db::getInstance()->execute(
            "UPDATE `" . _DB_PREFIX_ . "datacue_queue`
            SET `status` = $status,`executed_at` = NOW()
            WHERE `id_datacue_queue` in ($idsStr)"
        );
    }

    public static function deleteAllJobs()
    {
        return \Db::getInstance()->execute(
            "DELETE FROM `" . _DB_PREFIX_ . "datacue_queue`"
        );
    }
}
