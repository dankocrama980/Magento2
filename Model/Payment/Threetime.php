<?php
/**
 * Paybox Epayment module for Magento
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * available at : http://opensource.org/licenses/osl-3.0.php
 *
 * @package    Paybox_Epayment
 * @copyright  Copyright (c) 2013-2014 Paybox
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Paybox\Epayment\Model\Payment;

use \Magento\Sales\Model\Order;
use \Magento\Sales\Model\Order\Payment\Transaction;
use \Magento\Framework\Validator\Exception;

class Threetime extends AbstractPayment
{
    const CODE = 'pbxep_threetime';
    const XML_PATH = 'payment/pbxep_threetime/cctypes';

    protected $_code = self::CODE;
    protected $_3dsAllowed = true;
    protected $_hasCctypes = true;
    protected $_allowManualDebit = true;
    protected $_allowDeferredDebit = true;
    protected $_allowRefund = true;

    public function getReceipentEmail() {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->_scopeConfig->getValue(self::XML_PATH, $storeScope);
    }

    public function toOptionArray() {
        $result = array();
        $configPath = $this->getConfigPath();
        // $cards = Mage::getConfig()->getNode($configPath)->asArray();
        $cards = $this->_getConfigValue($configPath);
        if (!empty($cards)) {
            foreach ($cards as $code => $card) {
                $result[] = array(
                    'label' => __($card['label']),
                    'value' => $code,
                );
            }
        } else {
            $result[] = array(
                'label' => __('CB'),
                'value' => 'CB',
            );
            $result[] = array(
                'label' => __('Visa'),
                'value' => 'VISA',
            );
            $result[] = array(
                'label' => __('Mastercard'),
                'value' => 'EUROCARD_MASTERCARD',
            );
            $result[] = array(
                'label' => __('E-Carte Bleue'),
                'value' => 'E_CARD',
            );
        }
        return $result;
    }

    public function onIPNSuccess(Order $order, array $data) {
        $this->logDebug(sprintf('Order %s: Threetime IPN', $order->getIncrementId()));

        $this->logDebug(sprintf('onIPNSuccess :', $order->getIncrementId()));
        
        $payment = $order->getPayment();

        // Message

        // Create transaction
        $type = Transaction::TYPE_CAPTURE;
        $txn = $this->_addPayboxTransaction($order, $type, $data, true, array(
            self::CALL_NUMBER => $data['call'],
            self::TRANSACTION_NUMBER => $data['transaction'],
        ));
        
        if (is_null($payment->getPbxepFirstPayment())) {
            $this->logDebug(sprintf('Order %s: First payment', $order->getIncrementId()));

            // Message
            $message = 'Payment was authorized and captured by Paybox.';

            // Status
            $status = $this->getConfigPaidStatus();
            $state = Order::STATE_PROCESSING;
            $allowedStates = array(
                Order::STATE_NEW,
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PROCESSING,
            );
            $current = $order->getState();
            if (in_array($current, $allowedStates)) {
                $this->logDebug(sprintf('Order %s: Change status to %s', $order->getIncrementId(), $state));
                $order->setState($state, $status, $message);
            } else {
                $order->addStatusHistoryComment($message);
            }
            
            // Additional informations
            $payment->setPbxepFirstPayment(serialize($data));
            $payment->setPbxepAuthorization(serialize($data));

            $this->logDebug(sprintf('Order %s: %s', $order->getIncrementId(), $message));

            // Create invoice is needed
            $invoice = $this->_createInvoice($payment, $order, $txn);
        } else if (is_null($payment->getPbxepSecondPayment())) {
            // Message
            $message = 'Second payment was captured by Paybox.';
            $order->addStatusHistoryComment($message);

            // Additional informations
            $payment->setPbxepSecondPayment(serialize($data));
            $this->logDebug(sprintf('Order %s: %s', $order->getIncrementId(), $message));
        } else if (is_null($payment->getPbxepThirdPayment())) {
            // Message
            $message = 'Third payment was captured by Paybox.';
            $order->addStatusHistoryComment($message);

            // Additional informations
            $payment->setPbxepThirdPayment(serialize($data));
            $this->logDebug(sprintf('Order %s: %s', $order->getIncrementId(), $message));
        } else {
            $this->logDebug(sprintf('Order %s: Invalid three-time payment status', $order->getIncrementId()));
            throw new \LogicException('Invalid three-time payment status');
        }
        $data['status'] = $message;

        // Associate data to payment
        $payment->setPbxepAction('three-time');
        
        $payment->save();
        $order->save();
    }
}
