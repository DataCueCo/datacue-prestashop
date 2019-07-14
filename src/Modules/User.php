<?php

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
