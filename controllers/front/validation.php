<?php
/*
* 2007-2015 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * author: Cristian Tejada - https://github.com/ctejadan
 */

/**
 * @since 1.5.0
 */

require_once(_PS_MODULE_DIR_ . 'pagofacil' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'pfhelper' . DIRECTORY_SEPARATOR . 'PagoFacilHelper.php');

class PagoFacilValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $PFHelper = new PagoFacilHelper();

        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'pagofacil') {
                $authorized = true;
                break;
            }
        }

        //if no customer, return to step 1 (just in case)
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        //get data
        $extra_vars = array();
        $currency = new Currency($cart->id_currency);
        $cart_amount = Context::getContext()->cart->getOrderTotal(true);
        $customer_email = Context::getContext()->customer->email;
        $token_service = Configuration::get('TOKEN_SERVICE');
        $token_secret = Configuration::get('TOKEN_SECRET');
        $token_store = md5(date('m/d/Y h:i:s a', time()) . $cart->id . $token_service);

        //setting order as pending payment
        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAGOFACIL_PENDING_PAYMENT'), $cart_amount, $this->module->displayName, NULL, $extra_vars, (int)$currency->id, false, $customer->secure_key);

        //getting order_id
        $order_id = Order::getOrderByCartId((int)($cart->id));

        // Complete URL on order confirmation page
        $shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
        $url = Tools::getShopProtocol() .
            $shop->domain . $shop->getBaseURI();
        $return_url = $url .
            'index.php?controller=order-confirmation&id_cart=' .
            $cart->id . '&id_module=' . $this->module->id . '&id_order=' .
            $order_id . '&key=' . $customer->secure_key;

        //set payload
        $signaturePayload = array(
            'pf_amount' => $cart_amount,
            'pf_email' => $customer_email,
            'pf_order_id' => $order_id, //exist after validateOrder
            'pf_token_service' => $token_service,
            'pf_token_store' => $token_store,
            'pf_url_complete' => $return_url,
            'pf_url_callback' => $url . 'module/pagofacil/callback'
        );

        //generate signature
        $signature = $PFHelper->generateSignature($signaturePayload, $token_secret);

        //add signature to the payload
        $signaturePayload['pf_signature'] = $signature;

        //post parameters in string
        $postVars = $PFHelper->generatePostVarsString($signaturePayload);

        //create transaction in pago facil
        //create transaction in pago facil
        $resultBeforeJSONDecode = $PFHelper->createTransaction($postVars, $_REQUEST, Configuration::get('SHOW_ALL_PAYMENT_PLATFORMS'), Configuration::get('ENVIRONMENT'));

        $result = json_decode($resultBeforeJSONDecode, true);


        if (!empty($result) && array_key_exists("errorMessage", $result) || !empty($result) && array_key_exists("status", $result) && $result['status'] == 0) {
            $smarty = $this->context->smarty;
            $smarty->assign('errorCode', $result['statusCode']);
            $this->setTemplate('module:pagofacil/views/templates/front/create_transaction_failed.tpl');
        } else {
            if (empty($result)) {
                //show webpay
                echo $resultBeforeJSONDecode;
            } else {
                //redirect to payment platform
                Tools::redirect($result['redirect']);
            }
        }
    }

}
