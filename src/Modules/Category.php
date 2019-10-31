<?php

namespace DataCue\PrestaShop\Modules;

use DataCue\PrestaShop\Queue;
use DataCue\PrestaShop\Utils\Log;

/**
 * Class Category
 * @package DataCue\PrestaShop\Modules
 */
class Category
{
    /**
     * @param \Category $category
     * @param bool $withId
     * @return array
     */
    public static function buildCategoryForDataCue($category, $withId = false)
    {
        $item = [
            'name' => $category->getName(),
            'link' => $category->getLink(),
        ];

        if ($withId) {
            $item['category_id'] = $category->id;
        }

        return $item;
    }

    /**
     * @param $id
     * @return \Category
     */
    public static function getCategoryById($id)
    {
        return new \Category($id);
    }

    /**
     * @param $id
     * @return string
     */
    public static function getCategoryNameById($id)
    {
        return static::getCategoryById($id)->getName();
    }

    /**
     * @param \Category $category
     */
    public function onCategoryAdd($category)
    {
        Log::info('onCategoryAdd');

        Queue::addJob(
            'create',
            'categories',
            $category->id,
            [
                'item' => static::buildCategoryForDataCue($category, true),
            ]
        );
    }

    /**
     * @param \Category $category
     */
    public function onCategoryUpdate($category)
    {
        Log::info('onCategoryUpdate');

        Queue::addJob(
            'update',
            'categories',
            $category->id,
            [
                'categoryId' => $category->id,
                'item' => static::buildCategoryForDataCue($category, false),
            ]
        );
    }

    /**
     * @param \Category $category
     */
    public function onCategoryDelete($category)
    {
        Log::info('onCategoryDelete');

        Queue::addJob(
            'delete',
            'categories',
            $category->id,
            [
                'categoryId' => $category->id,
            ]
        );
    }
}
