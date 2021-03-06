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

namespace Paybox\Epayment\Block;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Paybox\Epayment\Gateway\Response\FraudHandler;

class Info extends ConfigurableInfo {

    protected $_object_manager;

    /**
     * Returns label
     *
     * @param string $field
     * @return Phrase
     */
    protected function getLabel($field) {
        return __($field);
    }

    /**
     * Returns value view
     *
     * @param string $field
     * @param string $value
     * @return string | Phrase
     */
    protected function getValueView($field, $value) {
        switch ($field) {
            case FraudHandler::FRAUD_MSG_LIST:
                return implode('; ', $value);
        }
        return parent::getValueView($field, $value);
    }

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('pbxep/info/default.phtml');
        $this->_object_manager = \Magento\Framework\App\ObjectManager::getInstance();
    }

    public function getCreditCards() {
        $result = array();
        $cards = $this->getMethod()->getCards();
        $selected = explode(',', $this->getMethod()->getConfigData('cctypes'));
        foreach ($cards as $code => $card) {
            if (in_array($code, $selected)) {
                $result[$code] = $card;
            }
        }
        return $result;
    }

    public function getPayboxData() {
        return unserialize($this->getInfo()->getPbxepAuthorization());
    }

    public function getObjectManager() {
        return $this->_object_manager;
    }

    public function getPayboxConfig() {
        return $this->_object_manager->get('Paybox\Epayment\Model\Config');
    }

    public function getCardImageUrl() {
         $data = $this->getPayboxData();
         $cards = $this->getCreditCards();
         if(!isset($data['cardType'])){
            return null;
        }
         return $this->getViewFileUrl('Paybox_Epayment::' . 'images/' .strtolower($data['cardType']).'.45.png', array('area'  => 'frontend', 'theme' => 'Magento/luma'));
    }

    public function getCardImageLabel() {
        $data = $this->getPayboxData();
        $cards = $this->getCreditCards();
        if(!isset($data['cardType'])){
            return null;
        }
        if (!isset($cards[$data['cardType']])) {
            return null;
        }
        $card = $cards[$data['cardType']];
        return $card['label'];
    }

    public function isAuthorized() {
        $info = $this->getInfo();
        $auth = $info->getPbxepAuthorization();
        return !empty($auth);
    }

    public function canCapture() {
        $info = $this->getInfo();
        $capture = $info->getPbxepCapture();
        $config = $this->getPayboxConfig();
        if ($config->getSubscription() == \Paybox\Epayment\Model\Config::SUBSCRIPTION_OFFER2 || $config->getSubscription() == \Paybox\Epayment\Model\Config::SUBSCRIPTION_OFFER3) {
            if ($info->getPbxepAction() == \Paybox\GenericPayment\Model\Payment\AbstractPayment::PBXACTION_MANUAL) {
                $order = $info->getOrder();
                return empty($capture) && $order->canInvoice();
            }
        }
        return false;
    }

    public function canRefund() {
        $info = $this->getInfo();
        $capture = $info->getPbxepCapture();
        $config = $this->getPayboxConfig();
        if ($config->getSubscription() == \Paybox\Epayment\Model\Config::SUBSCRIPTION_OFFER2 || $config->getSubscription() == \Paybox\Epayment\Model\Config::SUBSCRIPTION_OFFER3) {
            return !empty($capture);
        }
        return false;
    }

     public function getDebitTypeLabel() {
         $info = $this->getInfo();
         $action = $info->getPbxepAction();
         if (is_null($action) || ($action == 'three-time')) {
             return null;
         }
         
         $action = $info->getPbxepAction();
         $action_model = new \Paybox\Epayment\Model\Admin\Payment\Action();
         $actions = $action_model->toOptionArray();
         foreach($actions as $act){
             if($act['value'] == $action){
                 $result = $act['label'];
             }
         }
         if (($info->getPbxepAction() == \Paybox\Epayment\Model\Payment\AbstractPayment::PBXACTION_DEFERRED) && (!is_null($info->getPbxepDelay()))) {
             $delays = new \Paybox\Epayment\Model\Admin\Payment\Delays();
             $delays = $delays->toOptionArray();
             $result .= ' (' . $delays[$info->getPbxepDelay()]['label'] . ')';
         }
         return $result;
     }
    // public function getShowInfoToCustomer() {
    //     $config = $this->getPayboxConfig();
    //     return $config->getShowInfoToCustomer() != 0;
    // }
     public function getThreeTimeLabels() {
         $info = $this->getInfo();
         $action = $info->getPbxepAction();
         if (is_null($action) || ($action != 'three-time')) {
             return null;
         }
         $result = array(
             'first' => __('Not achieved'),
             'second' => __('Not achieved'),
             'third' => __('Not achieved'),
         );
         $data = $info->getPbxepFirstPayment();
         if (!empty($data)) {
             $data = unserialize($data);
             $date = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $data['date']);
             $result['first'] = sprintf('%s (%s)', $data['amount'] / 100.0, $date);
         }
         $data = $info->getPbxepSecondPayment();
         if (!empty($data)) {
             $data = unserialize($data);
             $date = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $data['date']);
             $result['second'] = sprintf('%s (%s)', $data['amount'] / 100.0, $date);
         }
         $data = $info->getPbxepThirdPayment();
         if (!empty($data)) {
             $data = unserialize($data);
             $date = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{4})$/', '$1/$2/$3', $data['date']);
             $result['third'] = sprintf('%s (%s)', $data['amount'] / 100.0, $date);
         }
         return $result;
     }

    public function getPartialCaptureUrl() {
        $data = $this->getPayboxData();
        $info = $this->getInfo();
        return $this->getUrl('paybox/partial', array('order_id' => $info->getOrder()->getId(), 'transaction' => $data['transaction']));
    }

    public function getCaptureUrl() {
        $data = $this->getPayboxData();
        $info = $this->getInfo();
        return $this->getUrl('paybox/capture', array('order_id' => $info->getOrder()->getId(), 'transaction' => $data['transaction']));
    }

    public function getRefundUrl() {
        $info = $this->getInfo();
        $order = $info->getOrder();
        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice) {
            if ($invoice->canRefund()) {
                return $this->getUrl('sales/order_creditmemo/start', array('order_id' => $order->getId(), 'invoice_id' => $invoice->getId()));
            }
        }
        return null;
    }

}
