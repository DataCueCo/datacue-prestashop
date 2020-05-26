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

namespace DataCue\PrestaShop\Widgets;

use DataCue\PrestaShop\Utils;

class Banner
{
    public function onDisplayNavFullWidth()
    {
        $show = \Configuration::get('DATACUE_PRESTASHOP_SHOW_BANNER', null);
        if ((int)$show === 1 && Utils::getAllValues()['controller'] === 'index') {
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
