<?php

namespace DataCue\PrestaShop\Widgets;


class Products
{
    public function onDisplayNavFullWidth()
    {
        $show = \Configuration::get('DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_HOME_PAGE', true);
        $type = \Configuration::get('DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_HOME_PAGE', true);
        if (intval($show) === 1 && \Tools::getAllValues()['controller'] === 'index') {
            echo '<div class="widget"><div data-dc-products="' . $type . '"></div></div>';
        }
    }

    public function onDisplayFooterProduct()
    {
        $show = \Configuration::get('DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_PRODUCT_PAGE', true);
        $type = \Configuration::get('DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_PRODUCT_PAGE', true);
        if (intval($show) === 1 && \Tools::getAllValues()['controller'] === 'product') {
            echo '<div class="widget"><div data-dc-products="' . $type . '"></div></div>';
        }
    }
}
