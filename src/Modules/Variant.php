<?php

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
        $item = [
            'name' => $product->name[1],
            'price' => static::getVariantPrice($product, $combination->id),
            'full_price' => static::getVariantFullPrice($product, $combination->id),
            'link' => \Context::getContext()->link->getProductLink($product, null, null, null, null, null, $combination->id),
            'available' => $product->active === '1' || $product->active === 1,
            'description' => $product->description[1],
            'photo_url' => empty($product->getCoverWs()) ? null : Utils::baseURL() . _PS_PROD_IMG_ . \Image::getImgFolderStatic($product->getCoverWs()) . $product->getCoverWs() . '.jpg',
            'stock' => \StockAvailable::getQuantityAvailableByProduct($product->id, $combination->id),
            'categories' => array_map(function ($categoryId) {
                return (new \Category($categoryId))->getName();
            }, $product->getCategories()),
            'main_category' => (new \Category($product->getDefaultCategory()))->getName(),
            'brand' => $product->getWsManufacturerName() ? $product->getWsManufacturerName() : null,
        ];
        if ($withId) {
            $item['product_id'] = $product->id;
            $item['variant_id'] = $combination->id;
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
            'create',
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
                'item' => static::buildVariantForDataCue($combination, $product, false),
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
                'create',
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
