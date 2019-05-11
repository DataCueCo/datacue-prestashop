<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use DataCue\PrestaShop\Events\BrowserEvents;
use DataCue\PrestaShop\Modules\Product;
use DataCue\PrestaShop\Modules\User;
use DataCue\PrestaShop\Modules\Variant;
use DataCue\PrestaShop\Modules\Order;
use DataCue\PrestaShop\Modules\Cart;
use DataCue\PrestaShop\Widgets\Banner;
use DataCue\PrestaShop\Common\Schedule;
use DataCue\PrestaShop\Common\Initializer;
use DataCue\Exceptions\UnauthorizedException;

class Datacue_prestashop extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'datacue_prestashop';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'DataCue.Co';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DataCue for PrestaShop');
        $this->description = $this->l('DataCue for PrestaShop');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('actionObjectCustomerAddAfter') &&
            $this->registerHook('actionObjectCustomerUpdateAfter') &&
            $this->registerHook('actionObjectCustomerDeleteAfter') &&
            $this->registerHook('actionObjectProductAddAfter') &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('actionObjectProductDeleteAfter') &&
            $this->registerHook('actionObjectCombinationAddAfter') &&
            $this->registerHook('actionObjectCombinationUpdateAfter') &&
            $this->registerHook('actionObjectCombinationDeleteAfter') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('actionObjectOrderDeleteAfter') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionCartSave') &&
            $this->registerHook('displayFooterAfter') &&
            $this->registerHook('displayBackOfficeFooter') &&
            $this->registerHook('displayNavFullWidth');
    }

    public function uninstall()
    {
        try {
            (new Initializer(
                Configuration::get('DATACUE_PRESTASHOP_API_KEY'),
                Configuration::get('DATACUE_PRESTASHOP_API_SECRET')
            ))->clearClient();
        } catch (Exception $e) {
        }

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->context->smarty->assign('module_dir', $this->_path);

        $output = '';

        try {
            if (((bool)Tools::isSubmit('submitDatacuePrestashopModule')) == true) {
                $this->postProcess();
                $output = $output.$this->context->smarty->fetch($this->local_path.'views/templates/admin/success.tpl');
            }
        } catch (UnauthorizedException $e) {
            $output = $output.$this->context->smarty->fetch($this->local_path.'views/templates/admin/unauthorizedError.tpl');
        } catch (Exception $e) {
            $output = $output.$this->context->smarty->fetch($this->local_path.'views/templates/admin/error.tpl');
        }

        return $output.$this->renderForm().$this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDatacuePrestashopModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
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
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Show Product Carousel'),
                        'name' => 'DATACUE_PRESTASHOP_SHOW_PRODUCT_CAROUSEL',
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
                        'type' => 'switch',
                        'label' => $this->l('Show Banner'),
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
                        'prefix' => '<i class="icon icon-image"></i>',
                        'desc' => $this->l('Enter the banner image url'),
                        'name' => 'DATACUE_PRESTASHOP_BANNER_IMAGE',
                        'label' => $this->l('Banner image'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-link"></i>',
                        'desc' => $this->l('Enter the banner link'),
                        'name' => 'DATACUE_PRESTASHOP_BANNER_LINK',
                        'label' => $this->l('Banner link'),
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
            'DATACUE_PRESTASHOP_API_KEY' => Configuration::get('DATACUE_PRESTASHOP_API_KEY', null),
            'DATACUE_PRESTASHOP_API_SECRET' => Configuration::get('DATACUE_PRESTASHOP_API_SECRET', null),
            'DATACUE_PRESTASHOP_SHOW_PRODUCT_CAROUSEL' => Configuration::get('DATACUE_PRESTASHOP_SHOW_PRODUCT_CAROUSEL', null),
            'DATACUE_PRESTASHOP_SHOW_BANNER' => Configuration::get('DATACUE_PRESTASHOP_SHOW_BANNER', null),
            'DATACUE_PRESTASHOP_BANNER_IMAGE' => Configuration::get('DATACUE_PRESTASHOP_BANNER_IMAGE', null),
            'DATACUE_PRESTASHOP_BANNER_LINK' => Configuration::get('DATACUE_PRESTASHOP_BANNER_LINK', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        (new Initializer(
            Tools::getValue('DATACUE_PRESTASHOP_API_KEY'),
            Tools::getValue('DATACUE_PRESTASHOP_API_SECRET')
        ))->maybeSyncData();

        $formValues = $this->getConfigFormValues();
        foreach (array_keys($formValues) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        (new BrowserEvents($this->context))->addJS();
    }

    public function hookActionObjectCustomerAddAfter($params)
    {
        (new User())->onUserAdd($params['object']);
    }

    public function hookActionObjectCustomerUpdateAfter($params)
    {
        (new User())->onUserUpdate($params['object']);
    }

    public function hookActionObjectCustomerDeleteAfter($params)
    {
        (new User())->onUserDelete($params['object']);
    }

    public function hookActionObjectProductAddAfter($params)
    {
        (new Product())->onProductAdd($params['object']);
    }

    public function hookActionProductUpdate($params)
    {
        (new Product())->onProductUpdate($params['product']);
    }

    public function hookActionObjectProductDeleteAfter($params)
    {
        (new Product())->onProductDelete($params['object']);
    }

    public function hookActionObjectCombinationAddAfter($params)
    {
        (new Variant())->onCombinationAdd($params['object']);
    }

    public function hookActionObjectCombinationUpdateAfter($params)
    {
        (new Variant())->onCombinationUpdate($params['object']);
    }

    public function hookActionObjectCombinationDeleteAfter($params)
    {
        (new Variant())->onCombinationDelete($params['object']);
    }

    public function hookActionValidateOrder($params)
    {
        (new Order())->onOrderAdd($params['order'], $params['currency']);
    }

    public function hookActionObjectOrderDeleteAfter($params)
    {
        (new Order())->onOrderDelete($params['object']);
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        (new Order())->onOrderStatusUpdate($params['id_order'], $params['newOrderStatus']);
    }

    public function hookActionCartSave()
    {
        (new Cart())->onCartSave();
    }

    public function hookDisplayFooterAfter()
    {
        (new Schedule())->maybeScheduleCron();
    }

    public function hookDisplayBackOfficeFooter()
    {
        (new Schedule())->maybeScheduleCron();
    }

    public function hookDisplayNavFullWidth()
    {
        (new Banner())->onDisplayNavFullWidth();
    }
}
