<?php

namespace DataCue\PrestaShop\Widgets;


class Banner
{
    public function onDisplayNavFullWidth()
    {
        $show = \Configuration::get('DATACUE_PRESTASHOP_SHOW_BANNER', null);
        if (intval($show) === 1) {
            $image = \Configuration::get('DATACUE_PRESTASHOP_BANNER_IMAGE', null);
            $link = \Configuration::get('DATACUE_PRESTASHOP_BANNER_LINK', null);
            echo <<<EOT
<div class="widget">
    <div
      data-dc-banners
      data-dc-static-img="$image"
      data-dc-static-link="$link"
    ></div>
</div>
EOT;
        }
    }
}
