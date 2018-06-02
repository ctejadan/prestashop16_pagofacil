<?php

/*
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * author: Cristian Tejada - https://github.com/ctejadan
 */

if (!defined('_PS_VERSION_')) {
    exit;
}
include_once 'vendor/autoload.php';
include_once(_PS_MODULE_DIR_ . 'pagofacil' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'PagoFacilHelper.php');


class PagoFacil extends PaymentModule
{

    var $token_service;
    var $token_secret;

    function __construct()
    {
        $this->name = 'pagofacil';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->author = 'Pago Fácil SPA';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 0;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('TOKEN_SERVICE', 'TOKEN_SECRET'));
        if (!empty($config['TOKEN_SERVICE'])) {
            $this->token_service = $config['TOKEN_SERVICE'];
        }
        if (!empty($config['TOKEN_SECRET'])) {
            $this->token_secret = $config['TOKEN_SECRET'];
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        if (!isset($this->token_secret) || !isset($this->token_service)) {
            $this->warning = $this->l('Token Service and Token Secret must be configured to continue.');
        }

        parent::__construct();

        $this->displayName = $this->l('Pago Fácil');
        $this->description = $this->l('Accept credit and debit card thanks to Pago Fácil platform.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

    }

    public function install()
    {

        if (!parent::install() OR !$this->registerHook('payment') OR !$this->registerHook('paymentReturn')) {
            return false;
        }

        /*
         * Generamos el nuevo estado de orden
         */
        if (!$this->installOrderState()) {
            return false;
        }


        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $token_service = strval(Tools::getValue('TOKEN_SERVICE'));
            $token_secret = strval(Tools::getValue('TOKEN_SECRET'));
            $environment = strval(Tools::getValue('ENVIRONMENT'));
            $show_all_payment_platforms = strval(Tools::getValue('SHOW_ALL_PAYMENT_PLATFORMS'));


            if (!$token_service || empty($token_service) || !Validate::isGenericName($token_service)) {
                $output .= $this->displayError($this->l('Wrong Token Service'));
                return $output . $this->displayForm();
            }
            if (!$token_secret || empty($token_secret) || !Validate::isGenericName($token_secret)) {
                $output .= $this->displayError($this->l('Wrong Token Secret'));
                return $output . $this->displayForm();
            }

            Configuration::updateValue('TOKEN_SERVICE', $token_service);
            Configuration::updateValue('TOKEN_SECRET', $token_secret);
            Configuration::updateValue('ENVIRONMENT', $environment);
            Configuration::updateValue('SHOW_ALL_PAYMENT_PLATFORMS', $show_all_payment_platforms);

            $output .= $this->displayConfirmation($this->l('Successfully updated'));
            $output .= $this->displayConfirmation($this->l("$token_service"));
            $output .= $this->displayConfirmation($this->l("$token_secret"));
            $output .= $this->displayConfirmation($this->l("$environment"));
            $output .= $this->displayConfirmation($this->l("$show_all_payment_platforms"));

        }
        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'label' => '',
                    'desc' => $this->l('To get the Token Service and Token Secret, you have to create an account in PagoFácil Dashboard:'),
                ),
                array(
                    'label' => '',
                    'desc' => $this->l('Integration, tests and development: https://dashboard.pagofacil.xyz'),
                ),
                array(
                    'label' => '',
                    'desc' => $this->l('Production: https://dashboard.pagofacil.org'),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Token Service'),
                    'name' => 'TOKEN_SERVICE',
                    'size' => 80,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Token Secret'),
                    'name' => 'TOKEN_SECRET',
                    'size' => 80,
                    'required' => true
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Environment:'),
                    'name' => 'ENVIRONMENT',
                    'desc' => $this->l('Remember, you have to choose the "Integration, tests and development" option you when you are using test credentials, these transactions are not real and you must use test data provided in our documentation https://docs.pagofacil.xyz.'),
                    'required' => true,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 'DEVELOPMENT',
                            'label' => $this->l('Integration, tests and development')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 'PRODUCTION',
                            'label' => $this->l('Production')
                        )
                    ),
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Use Pago Fácil platform to show payment options?'),
                    'name' => 'SHOW_ALL_PAYMENT_PLATFORMS',
                    'required' => true,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 'YES',
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 'NO',
                            'label' => $this->l('No')
                        )
                    ),
                ),
                array(
                    'label' => '',
                    'desc' => $this->l('Pago Fácil - https://www.pagofacil.org/'),
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['TOKEN_SERVICE'] = Configuration::get('TOKEN_SERVICE');
        $helper->fields_value['TOKEN_SECRET'] = Configuration::get('TOKEN_SECRET');
        $helper->fields_value['ENVIRONMENT'] = Configuration::get('ENVIRONMENT');
        $helper->fields_value['SHOW_ALL_PAYMENT_PLATFORMS'] = Configuration::get('SHOW_ALL_PAYMENT_PLATFORMS');


        return $helper->generateForm($fields_form);
    }

    /*
     * Con respecto a la visualización del pago al momento de realizar la compra.
     */

    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ));
        return $this->display(__FILE__, 'payment.tpl');
//        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /*
     * Se ejecuta cuando se finaliza el pago.
     * Lo llamamos desde return controller
     */


    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $objOrder = $params['objOrder'];
        $id_cart = $objOrder->id_cart;
        $order = new Order($id_cart);

        $state = $params['objOrder']->getCurrentState();
        $stateDB = $order->getCurrentState();
        $PS_OS_PAYMENT = Configuration::get('PS_OS_PAYMENT');

        if ($stateDB == $PS_OS_PAYMENT) {
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status' => 'ok',
                'id_order' => $order->id
            ));
            if (isset($order->reference) && !empty($order->reference)) {
                $this->smarty->assign('reference', $order->reference);
            }
        } else {
            $this->smarty->assign('status', 'failed');
        }
        return $this->display(__FILE__, 'payment_return.tpl');
    }


    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function installOrderState()
    {
        if (Configuration::get('PS_OS_PAGOFACIL_PENDING_PAYMENT') < 1) {
            $order_state = new OrderState();
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->invoice = false;
            $order_state->color = '#98c3ff';
            $order_state->logable = true;
            $order_state->shipped = false;
            $order_state->unremovable = false;
            $order_state->delivery = false;
            $order_state->hidden = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            $order_state->name = array((int)Configuration::get('PS_LANG_DEFAULT') => pSQL($this->l('Pago Fácil - Pendiente de Pago')));
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue('PS_OS_PAGOFACIL_PENDING_PAYMENT', $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

}
