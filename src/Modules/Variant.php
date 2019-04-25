<?php

namespace DataCue\PrestaShop\Modules;

use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Utils;

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
     * @return array
     */
    public static function buildVariantForDataCue($combination, $product = null, $withId = false)
    {
        if (is_null($product)) {
            $product = Product::getProductById($combination->id_product);
        }
        $item = [
            'name' => $product->name[1],
            'price' => $product->getPrice(false, $combination->id),
            'full_price' => (float)($product->price + $combination->price),
            'link' => $product->getLink(),
            'available' => $product->available_for_order === '1',
            'description' => $product->description[1],
            'photo_url' => Utils::baseURL() . _PS_PROD_IMG_ . \Image::getImgFolderStatic($product->getCoverWs()) . $product->getCoverWs() . '.jpg',
            'stock' => $combination->quantity,
            'categories' => array_map(function ($categoryId) {
                return (new \Category($categoryId))->getName();
            }, $product->getCategories()),
            'main_category' => (new \Category($product->getDefaultCategory()))->getName(),
        ];
        if ($withId) {
            $item['product_id'] = $product->id;
            $item['variant_id'] = $combination->id;
        }

        return $item;
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
    public function onCombinationAdd($combination)
    {
        $product = new \Product($combination->id_product);
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
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function onCombinationUpdate($combination)
    {
        $product = new \Product($combination->id_product);
        if ($job = Queue::getAliveJob('update', 'variants', $combination->id)) {
            Queue::updateJob(
                $job['id_datacue_queue'],
                [
                    'productId' => $product->id,
                    'variantId' => $combination->id,
                    'item' => static::buildVariantForDataCue($combination, $product, false),
                ]
            );
        } else {
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
    }

    /**
     * @param \Combination $combination
     */
    public function onCombinationDelete($combination)
    {
        Queue::addJob(
            'delete',
            'variants',
            $combination->id,
            [
                'productId' => $combination->id_product,
                'variantId' => $combination->id,
            ]
        );
    }
}
