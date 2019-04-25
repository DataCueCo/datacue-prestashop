<?php

namespace DataCue\PrestaShop\Modules;

/**
 * Class Category
 * @package DataCue\PrestaShop\Modules
 */
class Category
{
    /**
     * @param $id
     * @return string
     */
    public static function getCategoryNameById($id)
    {
        $category = new \Category($id);

        return $category->getName();
    }
}
