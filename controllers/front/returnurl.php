<?php

/*
 * Copyright 2017 Cristian Tala <yomismo@cristiantala.cl>.
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
 * Description of return
 *
 * @author Cristian Tala <yomismo@cristiantala.cl>
 */

use ctala\transaccion\classes\Response;

class PagoFacilReturnurlModuleFrontController extends ModuleFrontController {

    var $HTTPHelper;
    var $token_secret;
    var $token_service;

    public function initContent() {

        $this->HTTPHelper = new \ctala\HTTPHelper\HTTPHelper();
        $config = Configuration::getMultiple(array('TOKEN_SERVICE', 'TOKEN_SECRET'));
        $this->token_service = $config['TOKEN_SERVICE'];
        $this->token_secret = $config['TOKEN_SECRET'];


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log(print_r($_POST, true));
            $this->procesarReturnURL();
            $this->HTTPHelper->my_http_response_code(200);
        } else {
            $this->HTTPHelper->my_http_response_code(405);
        }
    }

    public function procesarReturnURL() {
        $order_id = Tools::getValue("ct_order_id");
        $cart = new Cart((int) $order_id);
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
                $cart->id_address_invoice == 0 || !$this->module->active) {
            $this->HTTPHelper->my_http_response_code(404);
        }

        // Check if customer exists
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->HTTPHelper->my_http_response_code(412);
        }

        //Obtenemos la orden
        $order = new Order($order_id);

        //Si la orden está completada no hago nada.
        $PS_OS_PAYMENT = Configuration::get('PS_OS_PAYMENT');
        if ($PS_OS_PAYMENT != $order->getCurrentState()) {



            $response = self::getResponseFromPost(INPUT_POST, $order_id);

            $ct_firma = filter_input(INPUT_POST, "ct_firma");
            $ct_estado = filter_input(INPUT_POST, "ct_estado");

            //Se firma el arreglo para poder comparar
            $response->setCt_token_secret($this->token_secret);
            $arregloFirmado = $response->getArrayResponse();

            if ($arregloFirmado["ct_firma"] == $ct_firma) {
                error_log("FIRMAS CORRESPONDEN");
                $ct_estado = $response->ct_estado;
                //Verifico el estado de la orden.
                if ($ct_estado == "COMPLETADA") {
                    //El pedido fue completado exitosamente.
                    //Corroboramos los montos.
                    if (round($order->total_paid) != $arregloFirmado["ct_monto"]) {
                        $this->HTTPHelper->my_http_response_code(400);
                    }
                    self::payment_completed($order);
                } else {
                    //TODO Si el pago no está completo marco como fallida
                }
            } else {
                error_log("FIRMAS NO CORRESPONDEN");
                $this->HTTPHelper->my_http_response_code(400);
            }
        }

        $this->redirectConfirmation($cart, $customer, $order_id);
    }

    public function returnError($result) {
        echo json_encode(array('error' => $result));
        exit;
    }

    public function returnSuccess($result) {
        echo json_encode(array('return_link' => $result));
        exit;
    }

    public function redirectConfirmation($cart, $customer, $order_id) {
        // Redirect on order confirmation page
        $shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
        $url = Tools::getShopProtocol() .
                $shop->domain . $shop->getBaseURI();
        $return_url = $url .
                'index.php?controller=order-confirmation&id_cart=' .
                $cart->id . '&id_module=' . $this->module->id . '&id_order=' .
                $order_id . '&key=' . $customer->secure_key;

        Tools::redirect($return_url);
    }

    protected static function getResponseFromPost($POST, $order_id) {
        $ct_order_id = $order_id;
        $ct_token_tienda = filter_input($POST, "ct_token_tienda");
        $ct_monto = filter_input($POST, "ct_monto");
        $ct_token_service = filter_input($POST, "ct_token_service");
        $ct_estado = filter_input($POST, "ct_estado");
        $ct_authorization_code = filter_input($POST, "ct_authorization_code");
        $ct_payment_type_code = filter_input($POST, "ct_payment_type_code");
        $ct_card_number = filter_input($POST, "ct_card_number");
        $ct_card_expiration_date = filter_input($POST, "ct_card_expiration_date");
        $ct_shares_number = filter_input($POST, "ct_shares_number");
        $ct_accounting_date = filter_input($POST, "ct_accounting_date");
        $ct_transaction_date = filter_input($POST, "ct_transaction_date");
        $ct_order_id_mall = filter_input($POST, "ct_order_id_mall");


        $response = new Response($ct_order_id, $ct_token_tienda, $ct_monto, $ct_token_service, $ct_estado, $ct_authorization_code, $ct_payment_type_code, $ct_card_number, $ct_card_expiration_date, $ct_shares_number, $ct_accounting_date, $ct_transaction_date, $ct_order_id_mall);
        return $response;
    }

    public static function payment_completed($order) {
        $PS_OS_PAYMENT = Configuration::get('PS_OS_PAYMENT');
        if ($PS_OS_PAYMENT != $order->getCurrentState()) {
            $order->setCurrentState($PS_OS_PAYMENT);
            $order->save();
        }
    }

}
