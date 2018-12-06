<?php
/**
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class paysafecash_gateway extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'paysafecash_gateway';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Prepaid Services Company Ltd.';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Paysafecash');
        $this->description = $this->l('Paysafecash ist eine Barzahlungsmethode. Generiere dir einen QR/Barcode und bezahle in einem Shop in deiner Nähe. Mehr Informationen und unsere Partnerfilialen findest du auf www.paysafecash.com');
        $this->confirmUninstall = $this->l('Möchten Sie diese Anwendung sicher entfernen?');

        $this->limited_countries = array('LU','ES', 'CH', 'DK', 'PL', 'IE', 'RO', 'BG', 'BE', 'HR', 'LV', 'AT', 'SI', 'NL', 'SK', 'CZ', 'FR', 'MT', 'HI', 'IT', 'PT', 'CA', 'DE' );

        $this->limited_currencies = array('EUR', 'CHF', 'USD', 'GBP');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false)
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        if (!$this->installOrderState()) {
            return false;
        }

        Configuration::updateValue('PAYSAFECASH_TEST_MODE', true);

       //include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->registerHook('displayPaymentTop');
    }
    public function installOrderState()
    {
        if (!Configuration::get('PAYSAFECASH_OS_WAITING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('PAYSAFECASH_OS_WAITING')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Awaiting for Paysafecash Payment';
            }
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->send_email = false;
            $order_state->color = '#7887e6';
            $order_state->invoice = false;
            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_.'paysafecash_gateway/logo.png';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.png';
                copy($source, $destination);
            }
            Configuration::updateValue('PAYSAFECASH_OS_WAITING', (int) $order_state->id);
        }
        if (!Configuration::get('PAYSAFECASH_OS_PAID')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('PAYSAFECASH_OS_PAID')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Paysafecash payed';
            }
            $order_state->hidden = false;
            $order_state->logable = true;
            $order_state->delivery = true;
            $order_state->send_email = false;
            $order_state->color = '#7887e6';
            $order_state->invoice = true;
            $order_state->add();

            Configuration::updateValue('PAYSAFECASH_OS_PAID', (int) $order_state->id);
        }
        return true;
    }

    public function uninstallOrderState()
    {
        $order_state = new OrderState(Configuration::get('PAYSAFECASH_OS_WAITING'));
        $order_state->delete();

        $order_state = new OrderState(Configuration::get('PAYSAFECASH_OS_PAID'));
        $order_state->delete();
    }


    public function uninstall()
    {
        $this->uninstallOrderState();

        Configuration::deleteByName('PAYSAFECASH_TEST_MODE');
        Configuration::deleteByName('PAYSAFECASH_API_KEY');
        Configuration::deleteByName('PAYSAFECASH_SUBMERCHANT_ID');
        Configuration::deleteByName('PAYSAFECASH_OS_WAITING');
        Configuration::deleteByName('PAYSAFECASH_OS_PAID');

        return parent::uninstall();
    }

    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitPaysafecashModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPaysafecashModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
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
                        'type' => 'switch',
                        'label' => $this->l('Test mode'),
                        'name' => 'PAYSAFECASH_TEST_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('If the test mode is enabled you are making transactions against paysafecash test environment. Therefore the test environment API key is necessary to be set.'),
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
                        'type' => 'password',
                        'col' => 3,
                        'name' => 'PAYSAFECASH_API_KEY',
                        'desc' => $this->l('This key is provided by the paysafecash support team. There is one key for the test- and one for production environment.'),
                        'label' => $this->l('API Key'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('This field specifies the used Reporting Criteria. You can use this parameter to distinguish your transactions per brand/URL. Use this field only if agreed beforehand with the paysafecash support team. The value has to be configured in both systems.'),
                        'name' => 'PAYSAFECASH_SUBMERCHANT_ID',
                        'label' => $this->l('Submerchant ID'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'PAYSAFECASH_TEST_MODE' => Configuration::get('PAYSAFECASH_TEST_MODE', true),
            'PAYSAFECASH_API_KEY' => Configuration::get('PAYSAFECASH_API_KEY', false),
            'PAYSAFECASH_SUBMERCHANT_ID' => Configuration::get('PAYSAFECASH_SUBMERCHANT_ID', null),
        );
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookPaymsentOptions($params)
    {
        exec('echo "Status Change: ' . print_r($params, true) . ' " >> /tmp/presta.log');
        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->trans('Zahlen mit Paysafecash', array(), 'paysafecash_gateway'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation($this->fetch('module:paysafecash_gateway/views/templates/hook/payment_info.tpl'));
        $payment_options = [
            $newOption,
        ];

        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        //$this->context->smarty->assign($this->getTemplateVarInfos());
        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->trans('Pay by Bank BNI', array(), 'Modules.BankBNI.Shop'))->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))->setAdditionalInformation($this->context->smarty->fetch('module:bankbni/views/templates/hook/intro.tpl'));
        $payment_options = [$newOption];
        return $payment_options;

        //exec('echo "Status Change: ' . print_r($payment_options, true) . ' " >> /tmp/presta.log');
        //return $payment_options;
    }

    public function hookPayment($params)
    {
        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false){
            return false;
        }

        $this->smarty->assign('module_dir', $this->_path);

       return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false)
            return;

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR'))
            $this->smarty->assign('status', 'ok');

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    public function hookDisplayPaymentReturn()
    {
        return $this->display(__FILE__, 'views/templates/front/redirectcustomer.tpl');
    }

    public function hookDisplayPaymentTop()
    {
        $this->smarty->assign('module_dir', $this->_path);

        echo $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }
}
