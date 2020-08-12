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
            'update',
            'categories',
            $category->id,
            [
                'categoryId' => $category->id,
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
                'item' => static::buildCategoryForDataCue($category, true),
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
