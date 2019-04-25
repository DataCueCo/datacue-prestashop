<?php

namespace DataCue\PrestaShop\Modules;

use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Utils;

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
            'price' => $product->getPrice(false),
            'full_price' => (float)$product->price,
            'link' => $product->getLink(),
            'available' => $product->available_for_order === '1',
            'description' => $product->description[1],
            'photo_url' => Utils::baseURL() . _PS_PROD_IMG_ . \Image::getImgFolderStatic($product->getCoverWs()) . $product->getCoverWs() . '.jpg',
            'stock' => $product->quantity,
            'categories' => array_map(function ($categoryId) {
                return (new \Category($categoryId))->getName();
            }, $product->getCategories()),
            'main_category' => (new \Category($product->getDefaultCategory()))->getName(),
        ];
        if ($withId) {
            $item['product_id'] = $product->id;
            $item['variant_id'] = 'no_variants';
        }

        return $item;
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
        if ($job = Queue::getAliveJob('update', 'products', $product->id)) {
            Queue::updateJob(
                $job['id_datacue_queue'],
                [
                    'productId' => $product->id,
                    'variantId' => 'no-variants',
                    'item' => static::buildProductForDataCue($product, false),
                ]
            );
        } else {
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
     * @param \Product $product
     */
    public function onProductDelete($product)
    {
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
