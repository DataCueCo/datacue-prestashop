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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';

class DataCue extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'datacue';
        $this->tab = 'advertising_marketing';
        $this->version = '1.3.0';
        $this->author = 'DataCue.Co';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->module_key = 'ab643ca3bc12cebf5cf610afb57c0de9';

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DataCue for PrestaShop');
        $this->description = $this->l('DataCue for PrestaShop');

        $this->confirmUninstall = $this->l('');

        try {
            \DataCue\Client::setIntegrationAndVersion('PrestaShop', $this->version);
        } catch (Exception $e) {
        }
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include(dirname(__FILE__) . '/sql/install.php');

        return parent::install() &&
            $this->installTab() &&
            $this->registerHook('header') &&
            $this->registerHook('actionObjectCustomerAddAfter') &&
            $this->registerHook('actionObjectCustomerUpdateAfter') &&
            $this->registerHook('actionObjectCustomerDeleteAfter') &&
            $this->registerHook('actionObjectProductAddAfter') &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('actionUpdateQuantity') &&
            $this->registerHook('actionObjectProductDeleteAfter') &&
            $this->registerHook('actionAdminProductsControllerActivateAfter') &&
            $this->registerHook('actionAdminProductsControllerDeactivateAfter') &&
            $this->registerHook('actionObjectCombinationAddAfter') &&
            $this->registerHook('actionObjectCombinationUpdateAfter') &&
            $this->registerHook('actionObjectCombinationDeleteAfter') &&
            $this->registerHook('actionObjectCategoryAddAfter') &&
            $this->registerHook('actionObjectCategoryUpdateAfter') &&
            $this->registerHook('actionObjectCategoryDeleteAfter') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('actionObjectOrderDeleteAfter') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionCartSave') &&
            $this->registerHook('displayFooterAfter') &&
            $this->registerHook('displayBackOfficeFooter') &&
            $this->registerHook('displayNavFullWidth') &&
            $this->registerHook('displayFooterProduct');
    }

    public function uninstall()
    {
        try {
            (new \DataCue\PrestaShop\Common\Initializer(
                Configuration::get('DATACUE_PRESTASHOP_API_KEY'),
                Configuration::get('DATACUE_PRESTASHOP_API_SECRET')
            ))->clearClient();
        } catch (Exception $e) {
        }

        include(dirname(__FILE__) . '/sql/uninstall.php');

        return parent::uninstall();
    }

    private function installTab()
    {
        $controllers = ['AdminDataCueSync', 'AdminDataCueDisconnect'];
        foreach ($controllers as $controller) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = $controller;
            $tab->name = [];
            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = $this->name;
            }
            $tab->id_parent = -1;
            $tab->module = $this->name;

            if (!$tab->save()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        \Media::addJsDef([
            'syncStatusUrl' => $this->context->link->getAdminLink('AdminDataCueSync'),
            'disconnectUrl' => $this->context->link->getAdminLink('AdminDataCueDisconnect'),
            'logUrlPrefix' => \DataCue\PrestaShop\Utils::baseURL() . '/modules/datacue/',
        ]);

        $this->context->controller->addCSS($this->_path . 'views/css/back.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/back.js', 'all');
        $this->context->smarty->assign('module_dir', $this->_path);

        $currentTab = 'base-settings';
        if ((bool)Tools::isSubmit('datacueBanners') || (bool)Tools::isSubmit('datacueProducts')) {
            $currentTab = 'recommendations';
        }

        return '
            <div class="form-wrapper">
                <ul class="nav nav-tabs">
                    <li class="' . ($currentTab === 'base-settings' ? 'active' : '') . '">
                        <a href="#base-settings" data-toggle="tab">Base Settings</a>
                    </li>
                    <li class="' . ($currentTab === 'recommendations' ? 'active' : '') . '">
                        <a href="#recommendations" data-toggle="tab">Recommendations</a>
                    </li>
                    <li class="' . ($currentTab === 'sync-status' ? 'active' : '') . '">
                        <a href="#sync-status" data-toggle="tab">Sync Status</a>
                    </li>
                    <li class="' . ($currentTab === 'logs' ? 'active' : '') . '">
                        <a href="#logs" data-toggle="tab">Logs</a>
                    </li>
                </ul>
                <div class="tab-content panel">
                    <div id="base-settings" class="tab-pane ' . ($currentTab === 'base-settings' ? 'active' : '') . '">
                        ' . $this->renderBaseSettingsTab()
            . $this->context->smarty
                ->fetch($this->local_path . 'views/templates/admin/baseSettingsFooter.tpl') . '
                    </div>
                    <div id="recommendations"
                        class="tab-pane ' . ($currentTab === 'recommendations' ? 'active' : '') . '">
                        ' . $this->renderBannersTab()
            . $this->context->smarty
                ->fetch($this->local_path . 'views/templates/admin/bannersFooter.tpl')
            . $this->renderProductsTab()
            . $this->context->smarty
                ->fetch($this->local_path . 'views/templates/admin/productsFooter.tpl')
            . $this->context->smarty
                ->fetch($this->local_path . 'views/templates/admin/recommendationsFooter.tpl') . '
                    </div>
                    <div id="sync-status" class="tab-pane ' . ($currentTab === 'sync-status' ? 'active' : '') . '">'
            . $this->context->smarty
                ->fetch($this->local_path . 'views/templates/admin/syncStatus.tpl') . '</div>
                    <div id="logs" class="tab-pane ' . ($currentTab === 'logs' ? 'active' : '') . '">'
            . $this->renderLogsTab() . '</div>
                </div>
            </div>
        ';
    }

    protected function renderBaseSettingsTab()
    {
        $output = '';
        $apiKey = Configuration::get('DATACUE_PRESTASHOP_API_KEY', null);
        $apiSecret = Configuration::get('DATACUE_PRESTASHOP_API_SECRET', null);

        if ((bool)Tools::isSubmit('datacueBaseSettings')) {
            $newKey = Tools::getValue('DATACUE_PRESTASHOP_API_KEY');
            $newSecret = Tools::getValue('DATACUE_PRESTASHOP_API_SECRET');

            if (trim($newKey) !== '' && trim($newSecret) !== '' && ($apiKey !== $newKey || $apiSecret !== $newSecret)) {
                try {
                    (new \DataCue\PrestaShop\Common\Initializer($newKey, $newSecret))->maybeSyncData();

                    $fieldKeys = ['DATACUE_PRESTASHOP_API_KEY', 'DATACUE_PRESTASHOP_API_SECRET'];
                    foreach ($fieldKeys as $key) {
                        Configuration::updateValue($key, Tools::getValue($key));
                    }
                    Configuration::updateValue('DATACUE_PRESTASHOP_CONNECTED', '1');
                    $output = $output . $this->context->smarty
                            ->fetch($this->local_path . 'views/templates/admin/success.tpl');
                    $apiKey = $newKey;
                    $apiSecret = $newSecret;
                } catch (\DataCue\Exceptions\UnauthorizedException $e) {
                    $output = $output . $this->context->smarty
                            ->fetch($this->local_path . 'views/templates/admin/unauthorizedError.tpl');
                } catch (Exception $e) {
                    $output = $output . $this->context->smarty
                            ->fetch($this->local_path . 'views/templates/admin/error.tpl');
                }
            }
        }

        $connected = Configuration::get('DATACUE_PRESTASHOP_CONNECTED', null) === '1';

        if ($connected) {
            $this->context->smarty->assign(['api_key' => $apiKey]);
            return $output . $this->context->smarty
                    ->fetch($this->local_path . 'views/templates/admin/disconnectSection.tpl');
        } else {
            return $output . $this->renderForm('datacueBaseSettings', 'baseSettingForm');
        }
    }

    protected function renderBannersTab()
    {
        $output = '';

        if ((bool)Tools::isSubmit('datacueBanners')) {
            $fieldKeys = [
                'DATACUE_PRESTASHOP_SHOW_BANNER',
                'DATACUE_PRESTASHOP_BANNER_IMAGE',
                'DATACUE_PRESTASHOP_BANNER_LINK',
            ];
            foreach ($fieldKeys as $key) {
                Configuration::updateValue($key, Tools::getValue($key));
            }
            $output = $output . $this->context->smarty
                    ->fetch($this->local_path . 'views/templates/admin/success.tpl');
        }

        return $output . $this->renderForm('datacueBanners', 'bannersForm');
    }

    protected function renderProductsTab()
    {
        $output = '';

        if ((bool)Tools::isSubmit('datacueProducts')) {
            $fieldKeys = [
                'DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_HOME_PAGE',
                'DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_HOME_PAGE',
                'DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_PRODUCT_PAGE',
                'DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_PRODUCT_PAGE',
            ];
            foreach ($fieldKeys as $key) {
                Configuration::updateValue($key, Tools::getValue($key));
            }
            $output = $output . $this->context->smarty
                    ->fetch($this->local_path . 'views/templates/admin/success.tpl');
        }

        return $output . $this->renderForm('datacueProducts', 'productsForm');
    }

    protected function renderLogsTab()
    {
        // Dates
        $dates = [];
        $timestamp = time();
        for ($i = 0; $i < 3; $i++) {
            $date = date('Y-m-d', $timestamp);
            if (file_exists(dirname(__FILE__) . "/datacue-$date.log")) {
                $dates[] = [
                    'value' => $date,
                    'selected' => count($dates) === 0 ? 'selected' : '',
                ];
            }
            $timestamp -= 24 * 3600;
        }

        $this->context->smarty->assign(['log_dates' => $dates]);
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/logs.tpl');
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm($action, $form)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = $action;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->{$form}()));
    }

    /**
     * Create the structure of your form.
     */
    protected function baseSettingForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Base Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-user"></i>',
                        'desc' => $this->l('Enter a valid api key'),
                        'name' => 'DATACUE_PRESTASHOP_API_KEY',
                        'label' => $this->l('Api Key'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-lock"></i>',
                        'desc' => $this->l('Enter a valid api secret'),
                        'name' => 'DATACUE_PRESTASHOP_API_SECRET',
                        'label' => $this->l('Api Secret'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Create the structure of your form.
     */
    protected function bannersForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Banners'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Add to home page'),
                        'name' => 'DATACUE_PRESTASHOP_SHOW_BANNER',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Static banner image'),
                        'prefix' => '<i class="icon icon-image"></i>',
                        'name' => 'DATACUE_PRESTASHOP_BANNER_IMAGE',
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Static banner URL'),
                        'prefix' => '<i class="icon icon-link"></i>',
                        'name' => 'DATACUE_PRESTASHOP_BANNER_LINK',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Create the structure of your form.
     */
    protected function productsForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Products'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Add to home page'),
                        'name' => 'DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_HOME_PAGE',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('recommendation type in home page'),
                        'name' => 'DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_HOME_PAGE',
                        'options' => array(
                            'query' => array(
                                array('id' => 'all', 'name' => 'All'),
                                array('id' => 'recent', 'name' => 'Recently Viewed'),
                                array('id' => 'similar', 'name' => 'Similar to current product'),
                                array('id' => 'related', 'name' => 'Related Products'),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Add to product page'),
                        'name' => 'DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_PRODUCT_PAGE',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('recommendation type in product page'),
                        'name' => 'DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_PRODUCT_PAGE',
                        'options' => array(
                            'query' => array(
                                array('id' => 'all', 'name' => 'All'),
                                array('id' => 'recent', 'name' => 'Recently Viewed'),
                                array('id' => 'similar', 'name' => 'Similar to current product'),
                                array('id' => 'related', 'name' => 'Related Products'),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'DATACUE_PRESTASHOP_API_KEY' =>
                Configuration::get('DATACUE_PRESTASHOP_API_KEY', null),
            'DATACUE_PRESTASHOP_API_SECRET' =>
                Configuration::get('DATACUE_PRESTASHOP_API_SECRET', null),
            'DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_HOME_PAGE'=>
                Configuration::get('DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_HOME_PAGE', null),
            'DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_HOME_PAGE' =>
                Configuration::get('DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_HOME_PAGE', null),
            'DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_PRODUCT_PAGE' =>
                Configuration::get('DATACUE_PRESTASHOP_SHOW_PRODUCTS_IN_PRODUCT_PAGE', null),
            'DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_PRODUCT_PAGE' =>
                Configuration::get('DATACUE_PRESTASHOP_PRODUCTS_TYPE_IN_PRODUCT_PAGE', null),
            'DATACUE_PRESTASHOP_SHOW_BANNER' =>
                Configuration::get('DATACUE_PRESTASHOP_SHOW_BANNER', null),
            'DATACUE_PRESTASHOP_BANNER_IMAGE' =>
                Configuration::get('DATACUE_PRESTASHOP_BANNER_IMAGE', null),
            'DATACUE_PRESTASHOP_BANNER_LINK' =>
                Configuration::get('DATACUE_PRESTASHOP_BANNER_LINK', null),
        );
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        (new \DataCue\PrestaShop\Events\BrowserEvents($this->context))->addJS();
    }

    public function hookActionObjectCustomerAddAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\User())->onUserAdd($params['object']);
    }

    public function hookActionObjectCustomerUpdateAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\User())->onUserUpdate($params['object']);
    }

    public function hookActionObjectCustomerDeleteAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\User())->onUserDelete($params['object']);
    }

    public function hookActionObjectProductAddAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Product())->onProductAdd($params['object']);
    }

    public function hookActionProductUpdate($params)
    {
        (new \DataCue\PrestaShop\Modules\Product())->onProductUpdate($params['product']);
    }

    public function hookActionUpdateQuantity($params)
    {
        (new \DataCue\PrestaShop\Modules\Product())->onProductQuantityUpdate(
            $params['id_product'],
            $params['id_product_attribute']
        );
    }

    public function hookActionObjectProductDeleteAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Product())->onProductDelete($params['object']);
    }

    public function hookActionAdminProductsControllerActivateAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Product())->onProductStatusUpdate($params['product_id']);
    }

    public function hookActionAdminProductsControllerDeactivateAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Product())->onProductStatusUpdate($params['product_id']);
    }

    public function hookActionObjectCombinationAddAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Variant())->onCombinationAdd($params['object']);
    }

    public function hookActionObjectCombinationUpdateAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Variant())->onCombinationUpdate($params['object']);
    }

    public function hookActionObjectCombinationDeleteAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Variant())->onCombinationDelete($params['object']);
    }

    public function hookActionObjectCategoryAddAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Category())->onCategoryAdd($params['object']);
    }

    public function hookActionObjectCategoryUpdateAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Category())->onCategoryUpdate($params['object']);
    }

    public function hookActionObjectCategoryDeleteAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Category())->onCategoryDelete($params['object']);
    }

    public function hookActionValidateOrder($params)
    {
        (new \DataCue\PrestaShop\Modules\Order())->onOrderAdd($params['order'], $params['currency']);
    }

    public function hookActionObjectOrderDeleteAfter($params)
    {
        (new \DataCue\PrestaShop\Modules\Order())->onOrderDelete($params['object']);
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        (new \DataCue\PrestaShop\Modules\Order())->onOrderStatusUpdate($params['id_order'], $params['newOrderStatus']);
    }

    public function hookActionCartSave()
    {
        (new \DataCue\PrestaShop\Modules\Cart())->onCartSave();
    }

    public function hookDisplayFooterAfter()
    {
        (new \DataCue\PrestaShop\Common\ReSync())->maybeScheduleCron();
        (new \DataCue\PrestaShop\Common\Schedule())->maybeScheduleCron();
    }

    public function hookDisplayBackOfficeFooter()
    {
        (new \DataCue\PrestaShop\Common\ReSync())->maybeScheduleCron();
        (new \DataCue\PrestaShop\Common\Schedule())->maybeScheduleCron();
    }

    public function hookDisplayNavFullWidth()
    {
        (new \DataCue\PrestaShop\Widgets\Banner())->onDisplayNavFullWidth();
        (new \DataCue\PrestaShop\Widgets\Products())->onDisplayNavFullWidth();
    }

    public function hookDisplayFooterProduct()
    {
        (new \DataCue\PrestaShop\Widgets\Products())->onDisplayFooterProduct();
    }
}
