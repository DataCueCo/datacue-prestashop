<?php

namespace DataCue\PrestaShop\Modules;

use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Utils;
use DataCue\PrestaShop\Utils\Log;

/**
 * Class Product
 * @package DataCue\PrestaShop\Modules
 */
class Product
{
    /**
     * @param \Product $product
     * @param bool $withId
     * @return array
     */
    public static function buildProductForDataCue($product, $withId = false)
    {
        $item = [
            'name' => $product->name[1],
            'price' => static::getProductPrice($product),
            'full_price' => static::getProductFullPrice($product),
            'link' => $product->getLink(),
            'available' => $product->active === '1' || $product->active === 1,
            'description' => $product->description[1],
            'photo_url' => empty($product->getCoverWs()) ? null : Utils::baseURL() . _PS_PROD_IMG_ . \Image::getImgFolderStatic($product->getCoverWs()) . $product->getCoverWs() . '.jpg',
            'stock' => \StockAvailable::getQuantityAvailableByProduct($product->id),
            'category_ids' => $product->getCategories(),
            'brand' => $product->getWsManufacturerName() ? $product->getWsManufacturerName() : null,
        ];
        if ($withId) {
            $item['product_id'] = "" . $product->id;
            $item['variant_id'] = 'no-variants';
        }

        return $item;
    }

    /**
     * get product real price
     *
     * @param \Product $product
     * @return float
     */
    public static function getProductPrice($product)
    {
        return $product->getPrice(true);
    }

    /**
     * get product full price
     * 
     * @param \Product $product
     * @return float
     */
    public static function getProductFullPrice($product)
    {
        return $product->getPrice(true, null, 6, null, false, false);
    }

    /**
     * @param $productId
     * @return \Product
     */
    public static function getProductById($productId)
    {
        return new \Product($productId);
    }

    /**
     * @param \Product $product
     */
    public function onProductAdd($product)
    {
        Log::info('onProductAdd');
        Queue::addJob(
            'create',
            'products',
            $product->id,
            [
                'productId' => $product->id,
                'variantId' => 'no-variants',
                'item' => static::buildProductForDataCue($product, true),
            ]
        );
    }

    /**
     * @param \Product $product
     */
    public function onProductUpdate($product)
    {
        Log::info('onProductUpdate');
        $combinations = $product->getWsCombinations();
        Log::info('combinations count = ' . count($combinations));
        if (count($combinations) === 0) {
            Queue::addJob(
                'update',
                'products',
                $product->id,
                [
                    'productId' => $product->id,
                    'variantId' => 'no-variants',
                    'item' => static::buildProductForDataCue($product, false),
                ]
            );
        }
    }

    /**
     * @param int $productId
     * @param int $variantId
     * @return void
     */
    public function onProductQuantityUpdate($productId, $variantId)
    {
        Log::info('onProductQuantityUpdate');
        if (empty($variantId)) {
            $product = static::getProductById($productId);
            $combinations = $product->getWsCombinations();
            if (count($combinations) === 0) {
                Queue::addJob(
                    'update',
                    'products',
                    $productId,
                    [
                        'productId' => $productId,
                        'variantId' => 'no-variants',
                        'item' => static::buildProductForDataCue($product, false),
                    ]
                );
            }
        } else {
            (new Variant())->onCombinationUpdate(Variant::getVariantById($variantId));
        }
    }

    /**
     * @param int $productId
     */
    public function onProductStatusUpdate($productId)
    {
        $product = static::getProductById($productId);
        $combinations = $product->getWsCombinations();
        foreach ($combinations as $item) {
            $combination = Variant::getVariantById($item['id']);
            Queue::addJob(
                'update',
                'variants',
                $combination->id,
                [
                    'productId' => $product->id,
                    'variantId' => $combination->id,
                    'item' => Variant::buildVariantForDataCue($combination, $product, false),
                ]
            );
        }
    }

    /**
     * @param \Product $product
     */
    public function onProductDelete($product)
    {
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
}
