<?php

class paysafecash_gatewayconfirmationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ((Tools::isSubmit('cart_id') == false) || (Tools::isSubmit('secure_key') == false)) {
            return false;
        }
        if(Tools::getValue('action') == "error"){
            Tools::redirect('index.php?controller=order');
        }

        //$this->setTemplate('module:paysafecash_gateway/views/templates/front/confirmation.tpl');

        require_once(_PS_MODULE_DIR_ . $this->module->name . "/libs/PaymentClass.php");


        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');
        $payment_id = Tools::getValue('payment_id');

        $cart = new Cart((int)$cart_id);
        $customer = new Customer((int)$cart->id_customer);

        $payment_status = Configuration::get('PAYSAFECASH_OS_WAITING'); // Default value for a payment that succeed.
        $message = $payment_id;

        $module_name = $this->module->name;
        $order_id = Order::getOrderByCartId((int)$cart->id);

        $this->testmode = "TEST";

        if ( $this->testmode ) {
            $env = "TEST";
        } else {
            $env = "PRODUCTION";
        }

        $pscpayment = new PaysafecardCashController( Configuration::get('PAYSAFECASH_API_KEY'), $env );
        $response   = $pscpayment->retrievePayment( $payment_id );

        $cart = new Cart((int)$cart_id);
        $currency_id = $cart->id_currency;
        $order = new Order($order_id);

        if ( $response == false ) {

        } else if ( isset( $response["object"] ) ) {
            if ( $response["status"] == "SUCCESS" ) {
                exec('echo " FRONT: Payment Success: '.print_r($response, true).'" >> /tmp/presta.log');
                if ($order->getCurrentState() ==  Configuration::get('PAYSAFECASH_OS_WAITING')) {
                    $order->setCurrentState(Configuration::get('PAYSAFECASH_OS_PAID'));
                    exec('echo "FRONT:  Payment Success Front: Set status" >> /tmp/presta.log');
                    $val = $this->module->validateOrder($cart_id, $payment_status, $cart->getOrderTotal(), $module_name, $message, array(), $currency_id, false, $secure_key);
                    exec('echo " Payment Validate Front: '.$val.' " >> /tmp/presta.log');
                    Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
                }else{
                    $val = $this->module->validateOrder($cart_id, $payment_status, $cart->getOrderTotal(), $module_name, $message, array(), $currency_id, false, $secure_key);
                    exec('echo "FRONT:  Payment Validate Front: '.$val.' " >> /tmp/presta.log');
                    Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
                }
            } else if ( $response["status"] == "INITIATED" ) {
                $this->module->validateOrder($cart_id, (int)Configuration::get('PAYSAFECASH_OS_WAITING'), $cart->getOrderTotal(), $module_name, $payment_id, array(), $currency_id, false, $secure_key);
                Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
            } else if ( $response["status"] == "REDIRECTED" ) {
                $this->module->validateOrder($cart_id, (int)Configuration::get('PAYSAFECASH_OS_WAITING'), $cart->getOrderTotal(), $module_name, $payment_id, array(), $currency_id, false, $secure_key);
                Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
            } else if ( $response["status"] == "EXPIRED" ) {
            } else if ( $response["status"] == "AUTHORIZED" ) {
                $response = $pscpayment->capturePayment( $payment_id );
                exec('echo "Authorized: '.print_r($response, true).'" >> /tmp/presta.log');
                if ( $response == true ) {
                    exec('echo "FRONT:  Success Transaction before" >> /tmp/presta.log');
                    if ( isset( $response["object"] ) ) {
                        if ( $response["status"] == "SUCCESS" ) {
                            exec('echo "FRONT:  Success Transaction Cart: '.$cart_id.'" >> /tmp/presta.log');
                            $this->module->validateOrder($cart_id, (int)Configuration::get('PAYSAFECASH_OS_PAID'), $cart->getOrderTotal(), $module_name, $message, array(), $currency_id, false, $secure_key);
                            if ($cart->OrderExists())
                            {
                                exec('echo "FRONT:  Success Order Transaction: '.$cart_id.'" >> /tmp/presta.log');
                                $new_history = new OrderHistory();
                                $new_history->id_order = (int)$order->id;
                                $new_history->changeIdOrderState((int)Configuration::get('PAYSAFECASH_OS_PAID'), $order, true);
                                $new_history->addWithemail(true);
                            }
                            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key.'payment='.$payment_id);
                        }
                    }
                }
            }
        }


    }
}

