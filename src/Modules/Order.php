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
            'user_id' => $order->id_customer,
            'timestamp' => str_replace('+00:00', 'Z', gmdate('c', strtotime($order->date_add))),
        ];

        $orderDetailList = $order->getOrderDetailList();
        $item['cart'] = array_map(function ($item) use ($currency) {
            return [
                'product_id' => $item['product_id'],
                'variant_id' => intval($item['product_attribute_id']) > 0 ? $item['product_attribute_id'] : 'no_variants',
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
