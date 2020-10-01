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
 * Class Variant
 * @package DataCue\PrestaShop\Modules
 */
class Variant
{
    /**
     * @param \Combination $combination
     * @param \Product|null $product
     * @param bool $withId
     * @return array|null
     */
    public static function buildVariantForDataCue($combination, $product = null, $withId = false)
    {
        if (is_null($product)) {
            $product = new \Product($combination->id_product);
            if (empty($product->id)) {
                return null;
            }
        }

        // get product image id
        $images = \Product::getCover($product->id);
        if (!empty($images) && array_key_exists('id_image', $images) && !empty($images['id_image'])) {
            $imageId = $images['id_image'];
        } else {
            $imageId = null;
        }

        $item = [
            'name' => $product->name[1],
            'price' => static::getVariantPrice($product, $combination->id),
            'full_price' => static::getVariantFullPrice($product, $combination->id),
            'link' => \Context::getContext()->link->getProductLink(
                $product,
                null,
                null,
                null,
                null,
                null,
                $combination->id
            ),
            'available' => $product->active === '1' || $product->active === 1,
            'description' => $product->description[1],
            'photo_url' => !empty($imageId) ? \Context::getContext()->link->getImageLink($product->link_rewrite, $imageId, \ImageType::getFormatedName('home')) : null,
            'stock' => \StockAvailable::getQuantityAvailableByProduct($product->id, $combination->id),
            'category_ids' => $product->getCategories(),
            'brand' => $product->getWsManufacturerName() ? $product->getWsManufacturerName() : null,
        ];
        if ($withId) {
            $item['product_id'] = "" . $product->id;
            $item['variant_id'] = "" . $combination->id;
        }

        return $item;
    }

    /**
     * get variant real price
     *
     * @param \Product $product
     * @param int $variantId
     * @return float
     */
    public static function getVariantPrice($product, $variantId)
    {
        return $product->getPrice(true, $variantId);
    }

    /**
     * get variant full price
     *
     * @param \Product $product
     * @param int $variantId
     * @return float
     */
    public static function getVariantFullPrice($product, $variantId)
    {
        return $product->getPrice(true, $variantId, 6, null, false, false);
    }

    /**
     * @param $variantId
     * @return \Combination
     */
    public static function getVariantById($variantId)
    {
        return new \Combination($variantId);
    }

    /**
     * @param \Combination $combination
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function onCombinationAdd($combination, $product = null)
    {
        Log::info('onCombinationAdd');
        if (is_null($product)) {
            $product = new \Product($combination->id_product);
        }

        $combinations = $product->getWsCombinations();
        Log::info('combinations count = ' . count($combinations));
        if (count($combinations) === 1) {
            Log::info('onProductDelete');
            Queue::addJob(
                'delete',
                'products',
                $product->id,
                [
                    'productId' => $product->id,
                    'variantId' => 'no-variants',
                ]
            );
        }

        Queue::addJob(
            'update',
            'variants',
            $combination->id,
            [
                'productId' => $product->id,
                'variantId' => $combination->id,
                'item' => static::buildVariantForDataCue($combination, $product, true),
            ]
        );
    }

    /**
     * @param \Combination $combination
     * @param \Product $product
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function onCombinationUpdate($combination, $product = null)
    {
        Log::info('onCombinationUpdate');
        if (is_null($product)) {
            $product = new \Product($combination->id_product);
        }
        Queue::addJob(
            'update',
            'variants',
            $combination->id,
            [
                'productId' => $product->id,
                'variantId' => $combination->id,
                'item' => static::buildVariantForDataCue($combination, $product, true),
            ]
        );
    }

    /**
     * @param \Combination $combination
     */
    public function onCombinationDelete($combination)
    {
        Log::info('onCombinationDelete');
        Queue::addJob(
            'delete',
            'variants',
            $combination->id,
            [
                'productId' => $combination->id_product,
                'variantId' => $combination->id,
            ]
        );

        $product = new \Product($combination->id_product);
        $combinations = $product->getWsCombinations();
        if (count($combinations) === 0 && !empty($product->id)) {
            Log::info('onProductAdd after all combinations deleted');
            Queue::addJob(
                'update',
                'products',
                $product->id,
                [
                    'productId' => $product->id,
                    'variantId' => 'no-variants',
                    'item' => Product::buildProductForDataCue($product, true),
                ]
            );
        }
    }
}
