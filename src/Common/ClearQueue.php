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

namespace DataCue\PrestaShop\Common;

use Configuration;
use DataCue\Client;
use DataCue\PrestaShop\Modules\Category;
use DataCue\PrestaShop\Utils\Log;
use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Modules\User;
use DataCue\PrestaShop\Modules\Product;
use DataCue\PrestaShop\Modules\Variant;
use DataCue\PrestaShop\Modules\Order;
use DataCue\PrestaShop\Utils;

class ClearQueue
{
    /**
     * Interval between two cron job.
     */
    const INTERVAL = 259200; // 3 days

    /**
     *
     */
    public function maybeScheduleCron()
    {
        $now = time();
        $lastJobTimestamp = Configuration::get('DATACUE_PRESTASHOP_LAST_CLEAR_QUEUE_TIMESTAMP', null);
        if ($lastJobTimestamp === false) {
            $lastJobTimestamp = 0;
        } else {
            $lastJobTimestamp = (int)$lastJobTimestamp;
        }

        if ($now - $lastJobTimestamp >= static::INTERVAL) {
            $this->executeCron();
            Configuration::updateValue('DATACUE_PRESTASHOP_LAST_CLEAR_QUEUE_TIMESTAMP', "$now");
        }
    }

    /**
     *
     */
    private function executeCron()
    {
        \Db::getInstance(_PS_USE_SQL_SLAVE_)->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'datacue_queue` WHERE `created_at` < CURDATE() - INTERVAL 3 DAY AND status in (2, 3)'
        );
    }
}
