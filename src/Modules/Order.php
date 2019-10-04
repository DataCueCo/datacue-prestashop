<?php

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
            'user_id' => $order->getCustomer()->isGuest() ? $order->getCustomer()->email : $order->id_customer,
            'timestamp' => str_replace('+00:00', 'Z', gmdate('c', strtotime($order->date_add))),
            'order_status' => intval($order->getCurrentState()) === 6 ? 'cancelled' : 'completed',
        ];

        $orderDetailList = $order->getOrderDetailList();
        $item['cart'] = array_map(function ($item) use ($currency) {
            return [
                'product_id' => $item['product_id'],
                'variant_id' => intval($item['product_attribute_id']) > 0 ? $item['product_attribute_id'] : 'no-variants',
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

        if ($order->getCustomer()->isGuest()) {
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
