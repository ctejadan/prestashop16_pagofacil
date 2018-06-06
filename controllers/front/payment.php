<?php

/*
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we cancd .. send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2016 PrestaShop SA
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


class PagoFacilPaymentModuleFrontController extends ModuleFrontController
{

    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $PFHelper = new PagoFacilHelper();

        // Disable left and right column
        $this->display_column_left = false;
        $this->display_column_right = false;
        parent::initContent();


        $this->token_service = Configuration::get('TOKEN_SERVICE');
        $this->token_secret = Configuration::get('TOKEN_SECRET');

        $cart = $this->context->cart;
        $total = $cart->getOrderTotal(true, Cart::BOTH);


        $currency = new Currency($cart->id_currency);
        $currency->iso_code;

        //get services for this account

        $result = $PFHelper->getServices(Configuration::get('ENVIRONMENT'), $currency->iso_code, $this->token_service);

        /* @var $smarty Smarty */
        $smarty = $this->context->smarty;
        $datos = array(
            'nbProducts' => $cart->nbProducts(),
            'cust_currency' => $cart->id_currency,
            'currencies' => $this->module->getCurrency((int)$cart->id_currency),
            'total' => $total,
            'this_path' => $this->module->getPathUri(),
            'this_path_bw' => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/',
            'result' => $result,
            'showAllPlatformsInPagoFacil' => Configuration::get('SHOW_ALL_PAYMENT_PLATFORMS')
        );

        $smarty->assign($datos);

        $this->setTemplate('payment_execution.tpl');

    }

}
