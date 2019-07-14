<?php

namespace DataCue\PrestaShop\Modules;

use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Utils;
use DataCue\PrestaShop\Utils\Log;

/**
 * Class Cart
 * @package DataCue\PrestaShop\Modules
 */
class Cart
{
    /**
     * @return array
     */
    public static function buildCartForDataCue()
    {
        $currency = \Currency::getDefaultCurrency();
        $products = \Context::getContext()->cart->getProducts();
        return array_map(function ($item) use ($currency) {
            return [
                'product_id' => $item['id_product'],
                'variant_id' => $item['id_product_attribute'] === '0' ? 'no-variants' : $item['id_product_attribute'],
                'quantity' => $item['cart_quantity'],
                'currency' => $currency->iso_code,
                'unit_price' => $item['price_with_reduction_without_tax'],
            ];
        }, $products);
    }

    /**
     *
     */
    public function onCartSave()
    {
        if (is_null(\Context::getContext()->cart)) {
            return;
        }

        Log::info('onCartSave');

        $cart = static::buildCartForDataCue();

        Queue::addJobWithoutModelId(
            'track',
            'events',
            [
                'user' => [
                    'user_id' => \Context::getContext()->customer->id,
                ],
                'event' => [
                    'type' => 'cart',
                    'subtype' => 'update',
                    'cart' => $cart,
                    'cart_link' => Utils::baseURL() . '/cart?action=show',
                ]
            ]
        );
    }
}
