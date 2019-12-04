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
        if (empty(\Context::getContext()->customer) ||
            empty(\Context::getContext()->customer->id) ||
            \Context::getContext()->customer->isGuest() || is_null(\Context::getContext()->cart)
        ) {
            return;
        }

        Log::info('onCartSave');

        $cart = static::buildCartForDataCue();
        $data = [
            'user' => [
                'user_id' => \Context::getContext()->customer->id,
            ],
            'event' => [
                'type' => 'cart',
                'subtype' => 'update',
                'cart' => $cart,
                'cart_link' => Utils::baseURL() . '/cart?action=show',
            ]
        ];

        if ($job = Queue::getAliveJob('track', 'events', \Context::getContext()->customer->id)) {
            Queue::updateJob($job['id_datacue_queue'], $data);
        } else {
            Queue::addJob(
                'track',
                'events',
                \Context::getContext()->customer->id,
                $data
            );
        }
    }
}
