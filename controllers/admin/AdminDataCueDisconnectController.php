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

use DataCue\Client;
use DataCue\PrestaShop\Utils;
use DataCue\PrestaShop\Queue;

class AdminDataCueDisconnectController extends ModuleAdminController
{
    /**
     * Max try times
     */
    const MAX_TRY_TIMES = 3;

    public function initContent()
    {
        parent::initContent();
        $this->ajax = true;
    }

    public function displayAjax()
    {
        $apiKey = Configuration::get('DATACUE_PRESTASHOP_API_KEY', null);
        $apiSecret = Configuration::get('DATACUE_PRESTASHOP_API_SECRET', null);

        try {
            $client = new Client(
                $apiKey,
                $apiSecret,
                ['max_try_times' => static::MAX_TRY_TIMES],
                Utils::isStaging() ? 'development' : 'production'
            );
            $client->client->clear();
        } catch (\Exception $e) {
        }

        Queue::deleteAllJobs();
        Configuration::deleteByName('DATACUE_PRESTASHOP_API_KEY');
        Configuration::deleteByName('DATACUE_PRESTASHOP_API_SECRET');
        Configuration::deleteByName('DATACUE_PRESTASHOP_CONNECTED');

        die(Tools::jsonEncode([]));
    }
}
