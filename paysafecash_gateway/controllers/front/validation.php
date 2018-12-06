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

class paysafecash_gatewayvalidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {


        //$this->setTemplate(__FILE__, 'views/templates/hook/payment.tpl');

        exec('echo "VAL: validation" >> /tmp/presta.log');
        require_once(_PS_MODULE_DIR_ . $this->module->name . "/libs/PaymentClass.php");

        $payment_id = Tools::getValue('payment_id');
        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');


        $payment_status = Configuration::get('PAYSAFECASH_OS_PAID');
        $message = "Payment: ".$payment_id;
        $module_name = $this->module->displayName;

        $this->testmode = "TEST";

        if ( $this->testmode ) {
            $env = "TEST";
        } else {
            $env = "PRODUCTION";
        }

        $pscpayment = new PaysafecardCashController( Configuration::get('PAYSAFECASH_API_KEY'), $env );
        $response   = $pscpayment->retrievePayment( $payment_id );

        exec('echo "VAL: Retrieve: '. print_r($response, true).'" >> /tmp/presta.log');


        $cart = new Cart((int)$cart_id);
        $currency_id = $cart->id_currency;
        $id_cart = $this->context->cart->id;
        $order = new Order(Order::getByCartId($cart->id));

        if ( $response == false ) {

        } else if ( isset( $response["object"] ) ) {
            if ( $response["status"] == "SUCCESS" ) {
                exec('echo "VAL:  Payment Success: '.print_r($response, true).'" >> /tmp/presta.log');
                if ($order->getCurrentState() ==  Configuration::get('PAYSAFECASH_OS_WAITING')) {
                    $order->setCurrentState(Configuration::get('PAYSAFECASH_OS_PAID'));
                    exec('echo "VAL:  Payment Success: Set status" >> /tmp/presta.txt');
                    return $this->module->validateOrder($cart_id, $payment_status, $cart->getOrderTotal(), $module_name, $message, array("transaction_id" => $payment_id), $currency_id, false, $secure_key);
                }
            } else if ( $response["status"] == "INITIATED" ) {
            } else if ( $response["status"] == "REDIRECTED" ) {
            } else if ( $response["status"] == "EXPIRED" ) {
            } else if ( $response["status"] == "AUTHORIZED" ) {
                $response = $pscpayment->capturePayment( $payment_id );
                exec('echo "VAL: Authorized: '.print_r($response, true).'" >> /tmp/presta.log');
                if ( $response == true ) {
                    exec('echo "VAL: Success Transaction before" >> /tmp/presta.txt');
                    if ( isset( $response["object"] ) ) {
                        if ( $response["status"] == "SUCCESS" ) {
                            exec('echo "VAL: Success Transaction Cart: '.$cart_id.'" >> /tmp/presta.log');
                            if ($cart->OrderExists())
                            {
                                exec('echo "VAL: Success Order Transaction: '.$cart_id.'" >> /tmp/presta.log');

                                $new_history = new OrderHistory();
                                $new_history->id_order = (int)$order->id;
                                $new_history->changeIdOrderState((int)Configuration::get('PAYSAFECASH_OS_PAID'), $order, true);
                                $new_history->addWithemail(true);

                                $order->setCurrentState(Configuration::get('PAYSAFECASH_OS_PAID'));
                                $order->save();
                            }

                            return $this->module->validateOrder($cart_id, $payment_status, $cart->getOrderTotal(), $module_name, $message, array(), $currency_id, false, $secure_key);
                        }
                    }
                }
            }
        }
    }
}
