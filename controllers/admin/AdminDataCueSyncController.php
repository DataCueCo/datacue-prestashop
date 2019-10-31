<?php

use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Common\Schedule;

class AdminDataCueSyncController extends ModuleAdminController
{
    public function initContent()
    {
        parent::initContent();
        $this->ajax = true;
    }

    public function displayAjax()
    {
        (new Schedule())->maybeScheduleCron();

        $res = [
            'categories' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'products' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'variants' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'users' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'orders' => [
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
        ];
        $items = Queue::getAllByAction('init');
        foreach($items as $item) {
            $count = count($item['job']->ids);
            $res[$item['model']]['total'] += $count;
            if (intval($item['status']) === Queue::STATUS_SUCCESS) {
                $res[$item['model']]['completed'] += $count;
            } elseif (intval($item['status']) === Queue::STATUS_FAILURE) {
                $res[$item['model']]['failed'] += $count;
            }
        }

        // return $this->json($res);
        die(Tools::jsonEncode($res));
    }
}