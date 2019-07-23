<?php

namespace DataCue\PrestaShop\Events;

use DataCue\PrestaShop\Modules\Cart;
use DataCue\PrestaShop\Modules\Product;
use Tools;
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
        $this->values = Tools::getAllValues();
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
            'category_name' => Category::getCategoryNameById($this->values['id_category']),
        ]);
        $this->addPublicJS();
    }

    /**
     *
     */
    private function addJSToProductPage()
    {
        $this->addDatacueConfig([
            'page_type' => 'product',
            'product_id' => $this->values['id_product'],
            'product_update' => Product::buildProductForDataCue(Product::getProductById($this->values['id_product']), true),
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
        $this->context->controller->registerJavascript(
            'datacue-checkout-page',
            'modules/datacue_prestashop/views/js/checkout_page.js',
            ['position' => 'bottom', 'priority' => 300]
        );
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
