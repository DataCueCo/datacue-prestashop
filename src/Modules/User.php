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

namespace DataCue\PrestaShop\Modules;

use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Utils\Log;

/**
 * Class User
 * @package DataCue\PrestaShop\Modules
 */
class User
{
    /**
     * @param \Customer $customer
     * @return array
     */
    public static function buildUserForDataCue($customer, $withId = false)
    {
        $item = [
            'email' => $customer->email,
            'timestamp' => str_replace('+00:00', 'Z', gmdate('c', strtotime($customer->date_add))),
            'first_name' => $customer->firstname,
            'last_name' => $customer->lastname,
        ];

        if ($withId) {
            $item['user_id'] = $customer->id;
        }

        return $item;
    }

    public static function getUserById($id)
    {
        return new \Customer($id);
    }

    /**
     * @param \Customer $customer
     */
    public function onUserAdd($customer)
    {
        if ($customer->isGuest()) {
            return;
        }
        Log::info('onUserAdd');
        Queue::addJob(
            'create',
            'users',
            $customer->id,
            [
                'item' => static::buildUserForDataCue($customer, true)
            ]
        );
    }

    /**
     * @param \Customer $customer
     */
    public function onUserUpdate($customer)
    {
        Log::info('onUserUpdate');
        if ($job = Queue::getAliveJob('update', 'users', $customer->id)) {
            Queue::updateJob(
                $job['id_datacue_queue'],
                [
                    'userId' => $customer->id,
                    'item' => static::buildUserForDataCue($customer, false),
                ]
            );
        } else {
            Queue::addJob(
                'update',
                'users',
                $customer->id,
                [
                    'userId' => $customer->id,
                    'item' => static::buildUserForDataCue($customer, false)
                ]
            );
        }
    }

    /**
     * @param \Customer $customer
     */
    public function onUserDelete($customer)
    {
        Log::info('onUserDelete');
        Queue::addJob(
            'delete',
            'users',
            $customer->id,
            [
                'userId' => $customer->id,
            ]
        );
    }
}
