<?php

namespace DataCue\PrestaShop\Widgets;


class Products
{
    public function onDisplayNavFullWidth()
    {
        $show = \Configuration::get('DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_HOME_PAGE', true);
        if (intval($show) === 1) {
            echo '<div class="widget"><div data-dc-product-carousels></div></div>';
        }
    }
}
