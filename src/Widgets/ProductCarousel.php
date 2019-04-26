<?php

namespace DataCue\PrestaShop\Widgets;


class ProductCarousel
{
    public function onDisplayNavFullWidth()
    {
        $show = \Configuration::get('DATACUE_PRESTASHOP_SHOW_PRODUCT_CAROUSEL', true);
        if (intval($show) === 1) {
            echo '<div class="widget"><div data-dc-product-carousels></div></div>';
        }
    }
}
