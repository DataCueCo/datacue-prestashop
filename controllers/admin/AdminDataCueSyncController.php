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
        $initExists = false;
        foreach ($items as $item) {
            $count = count($item['job']->ids);
            if (!$initExists && $count > 0) {
                $initExists = true;
            }
            $res[$item['model']]['total'] += $count;
            if ((int)$item['status'] === Queue::STATUS_SUCCESS) {
                $res[$item['model']]['completed'] += $count;
            } elseif ((int)$item['status'] === Queue::STATUS_FAILURE) {
                $res[$item['model']]['failed'] += $count;
            }
        }
        $res['init'] = $initExists;
        if (!$initExists) {
            $items = Queue::getQueueStatus();
            foreach ($items as $item) {
                if ($item['model'] === 'events') {
                    continue;
                }
                $res[$item['model']]['total'] += $item['total'];
                if ((int)$item['status'] === Queue::STATUS_SUCCESS) {
                    $res[$item['model']]['completed'] = $item['total'];
                } elseif ((int)$item['status'] === Queue::STATUS_FAILURE) {
                    $res[$item['model']]['failed'] = $item['total'];
                }
            }
        }
        // return $this->json($res);
        die(Tools::jsonEncode($res));
    }
}
