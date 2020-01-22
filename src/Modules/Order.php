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
 * Class Order
 * @package DataCue\PrestaShop\Modules
 */
class Order
{
    /**
     * @param \Order $order
     * @param \Currency $currency
     * @return array
     */
    public static function buildOrderForDataCue($order, $currency = null, $withId = false)
    {
        if (is_null($currency)) {
            $currency = \Currency::getDefaultCurrency();
        }

        $item = [
            'user_id' => static::getUserId($order),
            'timestamp' => str_replace('+00:00', 'Z', gmdate('c', strtotime($order->date_add))),
            'order_status' => (int)$order->getCurrentState() === 6 ? 'cancelled' : 'completed',
        ];

        $orderDetailList = $order->getOrderDetailList();
        $item['cart'] = array_map(function ($item) use ($currency) {
            return [
                'product_id' => $item['product_id'],
                'variant_id' => (int)$item['product_attribute_id'] > 0 ? $item['product_attribute_id'] : 'no-variants',
                'quantity' => $item['product_quantity'],
                'unit_price' => $item['unit_price_tax_excl'],
                'currency' => $currency->iso_code,
            ];
        }, $orderDetailList);

        if ($withId) {
            $item['order_id'] = $order->id;
        }

        return $item;
    }

    /**
     * @param \Order $order
     * @return array
     */
    public static function buildGuestUserForDataCue($order)
    {
        return [
            'user_id' => $order->getCustomer()->email,
            'email' => $order->getCustomer()->email,
            'title' => $order->getCustomer()->id_gender === 1 ? 'Mr' : 'Mrs',
            'first_name' => $order->getCustomer()->firstname,
            'last_name' => $order->getCustomer()->lastname,
            'email_subscriber' => false,
            'guest_account' => true,
        ];
    }

    /**
     * @param \Order $order
     * @return bool
     */
    public static function isEmailGuestOrder($order)
    {
        return $order->getCustomer()->isGuest();
    }

    /**
     * @param \Order $order
     * @return string
     */
    public static function getUserId($order)
    {
        if (!$order->getCustomer()->isGuest()) {
            return '' . $order->id_customer;
        }

        if (!empty($order->getCustomer()->email)) {
            return $order->getCustomer()->email;
        }

        return 'no-user';
    }

    /**
     * @param $id
     * @return \Order
     */
    public static function getOrderById($id)
    {
        return new \Order($id);
    }

    /**
     * @param \Order $order
     * @param \Currency $currency
     */
    public function onOrderAdd($order, $currency)
    {
        Log::info('onOrderAdd');

        if (static::isEmailGuestOrder($order)) {
            Queue::addJob(
                'create',
                'guest_users',
                $order->id,
                [
                    'item' => static::buildGuestUserForDataCue($order),
                ]
            );
        }
        Queue::addJob(
            'create',
            'orders',
            $order->id,
            [
                'item' => static::buildOrderForDataCue($order, $currency, true),
            ]
        );
    }

    /**
     * @param \Order $order
     */
    public function onOrderDelete($order)
    {
        Log::info('onOrderDelete');
        Queue::addJob(
            'delete',
            'orders',
            $order->id,
            [
                'orderId' => $order->id,
            ]
        );
    }

    /**
     * @param $orderId
     * @param \OrderState $newStatus
     */
    public function onOrderStatusUpdate($orderId, $newStatus)
    {
        if (!is_null($newStatus) && $newStatus->id === 6 && !Queue::isJobExisting('cancel', 'order', $orderId)) {
            Log::info('onOrderStatusUpdate');
            Queue::addJob(
                'cancel',
                'orders',
                $orderId,
                [
                    'orderId' => $orderId,
                ]
            );
        }
    }
}
