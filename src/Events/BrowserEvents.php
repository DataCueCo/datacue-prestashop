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

namespace DataCue\PrestaShop\Events;

use DataCue\PrestaShop\Modules\Cart;
use DataCue\PrestaShop\Modules\Product;
use DataCue\PrestaShop\Modules\Variant;
use Media;
use Configuration;
use DataCue\PrestaShop\Utils;
use DataCue\PrestaShop\Modules\Category;
use DataCue\PrestaShop\Modules\Order;

/**
 * Class BrowserEvents
 * @package DataCue\PrestaShop\Events
 */
class BrowserEvents
{
    /**
     * @var \Context
     */
    private $context;

    private $values;

    private $apiKey;

    /**
     * BrowserEvents constructor.
     * @param $context
     */
    public function __construct($context)
    {
        $this->context = $context;
        $this->values = Utils::getAllValues();
        $this->apiKey = Configuration::get('DATACUE_PRESTASHOP_API_KEY', null);
    }

    /**
     *
     */
    public function addJS()
    {
        if ($this->isHomePage()) {
            $this->addJSToHomePage();
        } elseif ($this->isCategoryPage()) {
            $this->addJSToCategoryPage();
        } elseif ($this->isProductPage()) {
            $this->addJSToProductPage();
        } elseif ($this->isCartPage()) {
            $this->addJSToCartPage();
        } elseif ($this->isSearchPage()) {
            $this->addJSToSearchPage();
        } elseif ($this->isCheckoutPage()) {
            $this->addJSToCheckoutPage();
        } elseif ($this->is404Page()) {
            $this->addJSTo404Page();
        } elseif ($this->isOrderConfirmationPage()) {
            $this->addJSToOrderConfirmationPage();
        }
    }

    /**
     * @return bool
     */
    private function isHomePage()
    {
        return $this->values['controller'] === 'index';
    }

    /**
     * @return bool
     */
    private function isCategoryPage()
    {
        return $this->values['controller'] === 'category';
    }

    /**
     * @return bool
     */
    private function isProductPage()
    {
        return $this->values['controller'] === 'product';
    }

    /**
     * @return bool
     */
    private function isCartPage()
    {
        return $this->values['controller'] === 'cart';
    }

    /**
     * @return bool
     */
    private function isSearchPage()
    {
        return $this->values['controller'] === 'search';
    }

    /**
     * @return bool
     */
    private function isCheckoutPage()
    {
        return $this->values['controller'] === 'order';
    }

    /**
     * @return bool
     */
    private function is404Page()
    {
        return $this->values['controller'] === 'pagenotfound';
    }

    /**
     * @return bool
     */
    private function isOrderConfirmationPage()
    {
        return $this->values['controller'] === 'orderconfirmation';
    }

    /**
     *
     */
    private function addJSToCartPage()
    {
        $this->addDatacueConfig([
            'page_type' => 'cart',
        ]);
        $this->addPublicJS();
    }

    /**
     *
     */
    private function addJSToHomePage()
    {
        $this->addDatacueConfig([
            'page_type' => 'home',
        ]);
        $this->addPublicJS();
    }

    /**
     *
     */
    private function addJSToCategoryPage()
    {
        $this->addDatacueConfig([
            'page_type' => 'category',
            'category_id' => "" . $this->values['id_category'],
            'category_update' =>
                Category::buildCategoryForDataCue(Category::getCategoryById($this->values['id_category'])),
        ]);
        $this->addPublicJS();
    }

    /**
     *
     */
    private function addJSToProductPage()
    {
        $product = Product::getProductById($this->values['id_product']);
        $variantIds = array_map(function ($item) {
            return $item['id'];
        }, $product->getWsCombinations());

        if (count($variantIds) > 0) {
            $productUpdate = array_map(function ($id) use ($product) {
                return Variant::buildVariantForDataCue(Variant::getVariantById($id), $product, true);
            }, $variantIds);
        } else {
            $productUpdate = [
                Product::buildProductForDataCue(Product::getProductById($this->values['id_product']), true)
            ];
        }
        $this->addDatacueConfig([
            'page_type' => 'product',
            'product_id' => "" . $this->values['id_product'],
            'product_update' => $productUpdate,
        ]);
        $this->addPublicJS();
    }

    /**
     *
     */
    private function addJSToSearchPage()
    {
        $this->addDatacueConfig([
            'page_type' => 'search',
            'term' => $this->values['s'],
        ]);
        $this->addPublicJS();
    }

    /**
     *
     */
    private function addJSToCheckoutPage()
    {
        $userId = \Context::getContext()->customer->id;
        $this->addDatacueConfig([
            'page_type' => 'checkout',
        ]);
        Media::addJsDef([
            'datacueUserId' => "$userId",
            'datacueCart' => Cart::buildCartForDataCue(),
            'datacueCartLink' => Utils::baseURL() . '/index.php?controller=cart&action=show',
        ]);
        $this->addPublicJS();
        if (Utils::is_1_6()) {
            $this->context->controller->addJS('/modules/datacue/views/js/checkout_page.js');
        } else {
            $this->context->controller->registerJavascript(
                'datacue-checkout-page',
                'modules/datacue/views/js/checkout_page.js',
                ['position' => 'bottom', 'priority' => 300]
            );
        }
    }

    /**
     *
     */
    private function addJSTo404Page()
    {
        $this->addDatacueConfig([
            'page_type' => '404',
        ]);
        $this->addPublicJS();
    }

    /**
     *
     */
    private function addJSToOrderConfirmationPage()
    {
        $orderId = $this->values['id_order'];
        $order = Order::getOrderById($orderId);
        if ($order->getCustomer()->isGuest()) {
            $userId = $order->getCustomer()->email;
        } else {
            $userId = '' . $order->getCustomer()->id;
        }
        $this->addDatacueConfig([
            'page_type' => 'order confirmation',
            'user_id' => $userId,
        ]);
        $this->addPublicJS();
    }

    /**
     *
     */
    private function addDatacueConfig($config)
    {
        $baseConfig = [
            'api_key' => $this->apiKey,
        ];

        if (Utils::isStaging()) {
            $baseConfig['options'] = [
                '_staging' => true,
            ];
        }

        $userId = $this->context->customer->id;
        if (!is_null($userId)) {
            $baseConfig['user_id'] = "$userId";
        } else {
            $baseConfig['user_id'] = null;
        }

        Media::addJsDef([
            'datacueConfig' => array_merge($baseConfig, $config),
        ]);
    }

    /**
     *
     */
    private function addPublicJS()
    {
        if (Utils::is_1_6()) {
            $this->context->controller->addJS('https://cdn.datacue.co/js/datacue.js');
            $this->context->controller->addJS('https://cdn.datacue.co/js/datacue-storefront.js');
        } else {
            $this->context->controller->registerJavascript(
                'datacue',
                'https://cdn.datacue.co/js/datacue.js',
                ['server' => 'remote', 'position' => 'bottom', 'priority' => 200]
            );
            $this->context->controller->registerJavascript(
                'datacue-storefront',
                'https://cdn.datacue.co/js/datacue-storefront.js',
                ['server' => 'remote', 'position' => 'bottom', 'priority' => 201]
            );
        }
    }
}
