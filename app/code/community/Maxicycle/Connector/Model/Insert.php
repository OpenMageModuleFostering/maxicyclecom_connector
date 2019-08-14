<?php

/**
 * Copyright (c) 2009-2014 Vaimo AB
 *
 * Vaimo reserves all rights in the Program as delivered. The Program
 * or any portion thereof may not be reproduced in any form whatsoever without
 * the written consent of Vaimo, except as provided by licence. A licence
 * under Vaimo's rights in the Program may be available directly from
 * Vaimo.
 *
 * Disclaimer:
 * THIS NOTICE MAY NOT BE REMOVED FROM THE PROGRAM BY ANY USER THEREOF.
 * THE PROGRAM IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE PROGRAM OR THE USE OR OTHER DEALINGS
 * IN THE PROGRAM.
 *
 * @category   Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015-2016 Maxicycle Software GmbH
 */

class Maxicycle_Connector_Model_Insert extends Mage_Core_Model_Abstract {
    
    var $_db = null;
    var $_dbResource = null;
    var $_enabled = NULL;
    var $_config = array();
    var $_quote_or_order = NULL;
    var $_campaign = NULL;
    var $_now = NULL;
    var $_is_type = NULL; // order or quote
    var $_store_id = NULL;
    
    public function __construct($params) {
        Mage::log('Maxicycle Campaign initialized for store ' . $params['store_id'], null, 'maxicycle.log');
        $this->_db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->_dbResource = Mage::getSingleton('core/resource');
        $this->setConfig($params['store_id']);
        $this->_store_id = $params['store_id'];
        $this->_quote_or_order = $params['quote_or_order'];
        $this->_now = date("Y-m-d H:i:s");  
        $this->_is_type = $params['is_type'];
    }     
    
    // get and set module configuration
    private function setConfig($store_id) {
        $this->_config = Mage::helper('maxicycle')->getConfiguration($store_id);        
    }    
    
    public function addInsert() {
        Mage::log('Checking for active campaigns', null, 'maxicycle.log');
        $active_campaigns = $this->_getActiveCampaigns();
        // If there is some active campaign
        if (count($active_campaigns) > 0) {
            $order = $this->_quote_or_order;
            Mage::log('Active campaigns, checking for product to add', null, 'maxicycle.log');
            // Loop over active campaings
            foreach ($active_campaigns as $campaign) {
                $this->_campaign = $campaign;
                $order->setMaxicycleCampaignId($campaign['campaign_id']);
                $this->_setCustomerIdToOrder();
                // Process condition - ADD PRODUCT - only if campaign is still in CP and not already in RT
                if (!$this->_inResponsePeriod($campaign)) { 
                    $this->_addProduct();      
                    $this->_quote_or_order->save();
                } else {
                  Mage::log('Not adding product, campaign in response period', null, 'maxicycle.log');              
                }
            }
        } else {
             Mage::log('No active campaigns, not adding a product', null, 'maxicycle.log');
        }
        Mage::log('adding insert done!', null, 'maxicycle.log');
    }
    
    private function _addProduct() {
        // if treatment -> insert SKU
        if ($this->_isTreatment()) {            
            Mage::log('Treament order', null, 'maxicycle.log');
            $this->checkAndAddProduct();
        } else {            
            Mage::log('OrderType: control', null, 'maxicycle.log');
            // Set it into order to be used when exporting the results
            $this->_quote_or_order->setMaxicycleOrderType('control');            
        }                
    }
    
    // Takes the product skus stored in campaigns and checks if they were already added
    // product_sku 'sku1:30;sku2:40;sku3:30'        
    private function checkAndAddProduct() {
        // if quote we want to check if sku is already on order to not add it twice 
        // like on log in on checkout
        $already_added = false;
        // Check if we have already added the sku
        // Either quote has added sku or a reload/log in on checkout
        if ($this->checkIfSKUAlreadyAdded()) {
            Mage::log('Product was already added to order', null, 'maxicycle.log');
            return;
        }          
        $product = $this->pickProduct();
        if ($product) {
             if ($this->_isOrder()) {
                $this->addOrderItem();    
             } else {
                $this->_addQuoteItem($product);              
             }
             // Mark order
             $this->_quote_or_order->setMaxicycleOrderType('treatment');                                            
             Mage::log('OrderType: treatment', null, 'maxicycle.log');                                    
        }
        else {
             Mage::log('OrderType: error, SKU not found', null, 'maxicycle.log');   
             $this->_quote_or_order->setMaxicycleOrderType('error');                            
        }

    }
    
    // Checks if Quote is already a Maxicycle order
    // if so, copies the order type and campaign id
    // returns campaign type or nil
    private function _alreadyMaxicycleOrder($order) {
        Mage::log('Check if quote is already maxicycle order', null, 'maxicycle.log');
        $quote_id = $order->getQuoteId();
        $quote = Mage::getModel('sales/quote')->load($quote_id);
        $campaignType = $quote->getMaxicycleOrderType(); 
        
        // if type already set,  copy values
        if (isset($campaignType)) {                   
            $order->setMaxicycleOrderType($quote->getMaxicycleOrderType());
            $order->setMaxicycleCampaignId($quote->getMaxicycleCampaignId());
            $order->setMaxicycleSku($quote->getMaxicycleSku());
        }
        return $campaignType;        
    }
    
    //   This method loops through all items and checks if one of them is on of the skus
    //    Array(
    //    [0] => voucher:30
    //    [1] => gimmik:50
    //    [2] => treat:20)
    private function checkIfSKUAlreadyAdded() {
        // $skus == ['sku1', 'sku2', 'sku3'] 
        $skus_array = explode(';', $this->_campaign['product_sku']); 
        $items = $this->_quote_or_order->getAllItems();
        
        $skus = $this->getSkus($skus_array);
        $added = FALSE;
        Foreach($items as $item){     
            if (in_array($item->getSku(), $skus)) {
                $added = TRUE;
            }
        }
        Mage::log('SKU already added?:' . $added, null, 'maxicycle.log');
        return $added;
    }
    
    // create and add order item to quote
    private function addOrderItem() {
        Mage::log('Adding product to order', null, 'maxicycle.log');
        $product = $this->pickProduct();
        $order = $this->_quote_or_order;
        if ($product) {
            try {
                // Save product into order
                $rowTotal = 0.00;
                $order_item = Mage::getModel('sales/order_item')
                        ->setStoreId($this->_store_id)
                        ->setQuoteItemId(null)
                        ->setQuoteParentItemId(null)
                        ->setProductId($product->getId())
                        ->setProductType($product->getTypeId())
                        ->setQtyBackordered(null)
                        ->setTotalQtyOrdered(1)
                        ->setQtyOrdered(1)
                        ->setName($product->getName())
                        ->setSku($product->getSku())
                        ->setPrice(0)
                        ->setBasePrice(0)
                        ->setOriginalPrice(0)
                        ->setRowTotal($rowTotal)
                        ->setBaseRowTotal($rowTotal)
                        ->setOrder($order);
                $order_item->save();     
                Mage::log('Product successfully added to order', null, 'maxicycle.log');
            } catch (Exception $e) {
                Mage::log('Adding product to order failed: ' .$e->getMessage(), null, 'maxicycle.log');
                throw $e;
            }
        }
    }
    // create and add order item to quote
    private function _addQuoteItem($product) {
        $quote = $this->_quote_or_order;
        if ($product->getId()) {
            try {
                // Save product into quote
                $rowTotal = 0.00;
                $order_item = Mage::getModel('sales/quote_item')
                ->setStoreId($this->_store_id)
                ->setQuoteId($quote->getId())
                ->setProduct($product)
                ->setQty(1)
                ->setPrice(0)
                ->setBasePrice(0)
                ->setCustomPrice(0)
                ->setOriginalCustomPrice(0)
                ->setRowTotal($rowTotal)
                ->setBaseRowTotal($rowTotal)
                ->setRowTotalWithDiscount($rowTotal)
                ->setRowTotalInclTax($rowTotal)
                ->setBaseRowTotalInclTax($rowTotal)
                ->setQuote($quote);
                $order_item->save(); 
                Mage::log('Product successfully added to quote', null, 'maxicycle.log');                
            } catch (Exception $e) {
                Mage::log('Adding product to quote failed: ' .$e->getMessage(), null, 'maxicycle.log');
                throw $e;
            }    
            return true;
        }
    }
    
    // returns sha1 for email
    private function customerId($email) {
        return sha1($email);
    }
    
    // get customer id and set it to order
    private function _setCustomerIdToOrder() {
        $order = $this->_quote_or_order;
        $campaign = $this->_campaign;
        // Customer email
        $customer_email = $order->getCustomerEmail();
        
        //Check if order exist with same customer email and already with Maxicycle Customer ID
        $maxicycle_customer_id_exist = $this->_db->fetchAll("SELECT maxicycle_customer_id FROM " 
                . $this->_dbResource->getTableName('sales/order') 
                . " WHERE customer_email = '$customer_email' AND store_id = " 
                . $campaign['store_id'] . " AND maxicycle_customer_id IS NOT NULL LIMIT 1");

        $maxicycle_customer_id = 0;

        // If not then assign maxicycle customer  as order entity ID is, if yes then use maxicycle_customer_id from the past
        if (count($maxicycle_customer_id_exist) > 0) {
            $maxicycle_customer_id = intval($maxicycle_customer_id_exist[0]['maxicycle_customer_id']);
            Mage::log('existing customer id found: ' . $maxicycle_customer_id, null, 'maxicycle.log');
        } else {
            $maxicycle_customer_id = $order->getEntityId();
            Mage::log('new customer id: ' . $maxicycle_customer_id, null, 'maxicycle.log');
        }
        $order->setMaxicycleCustomerId($maxicycle_customer_id);
        
        return $maxicycle_customer_id;
    }
    
    //   This method gets the campaign skus and loops through the array and randomly chooses the SKU
    //   
    //    Array(
    //    [0] => voucher:30
    //    [1] => gimmik:50
    //    [2] => treat:20)
    //   For backwards compatibility the entry can look also like
    //   [0] => voucher
    private function pickProduct() {
        $campaign = $this->_campaign;
        $skus = explode(';', $campaign['product_sku']); 
        // product_sku 'sku1:30;sku2:40;sku3:30'
        // $skus == ['sku1:30', 'sku2:40', 'sku3:30'] 
        // Generate random number
        $random_number = rand(0, 100);
        $percentage_section = 0; 
        $sku = NULL;
        //Mage::log('Picking product: random number ' .$random_number, null, 'maxicycle.log');
        foreach($skus as $sku_percentage) {
            $sku_p_ary = explode(':', $sku_percentage);
            // check if ary is a single SKU 
            if (count($sku_p_ary) == 1) { 
                Mage::log('Single SKU', null, 'maxicycle.log'); 
                $sku = $sku_p_ary[0]; // single sku like 'voucher', no percentage, return SKU straight away                
            } else {
              // split test,  multiple                 
              list($chosen, $percentage) = $sku_p_ary;
              $percentage_section += intval($percentage);
              if ($random_number <= $percentage_section) {
                Mage::log('Picking product: in percentile ' .$percentage_section, null, 'maxicycle.log');   
                $sku = $chosen;
                $this->_quote_or_order->setMaxicycleSku($sku);          
                break;
              } else {
                  Mage::log('Picking product: not in percentile ' .$percentage_section, null, 'maxicycle.log');   
              }
            }
        }
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        if (!$product) { Mage::log('WARNING: Product ' . $sku . ' not found for control order', null, 'maxicycle.log');}
        return $product;
    }
    
    //   Collect all skus in an array, skus is an array like below
    //   
    //    Array(
    //    [0] => voucher:30
    //    [1] => gimmik:50
    //    [2] => treat:20)
    //   For backwards compatibility the entry can look also like
    //   [0] => voucher
    private function getSkus($sku_array) {
        $skus = array();
        foreach($sku_array as $sku_percentage) {
          $sku_p_ary = explode(':', $sku_percentage);
          $skus[] = $sku_p_ary[0];
        }
        Mage::log('Campaign skus: ' . join(', ', $skus), null, 'maxicycle.log');
        return $skus;
    }
        
    // gets the treatment group size out of the condition coloumn
    private function _getTreatmentGroupSize() {
        // condition = treatment_group_size:90
        $condition = explode(":", $this->_campaign['condition']);
        return $condition[1];
    }
    
    // get all active campaigns running or in response time
    private function _getActiveCampaigns() {
        $camps = $this->_db->fetchAll("SELECT * FROM " . $this->_dbResource->getTableName('maxicycle/campaigns') 
                . " WHERE campaign_start <= '$this->_now' AND response_time_end >= '$this->_now' AND store_id = " . $this->_store_id);
        return $camps;
    }
    
    // check if campaign is in response period
    private function _inResponsePeriod($campaign) {
        $campaign_time_end = strtotime($campaign['campaign_end']);
        return ($campaign_time_end <= time());
    }
    
    // Generates random number and checks if treatment or not
    private function _isTreatment() {
        // check if order type was already set 
        // for order we need to check against quote
        if ($this->_isOrder()) {
            $orderType = $this->_alreadyMaxicycleOrder($this->_quote_or_order);
        }
        // for quote we simply check if set or not
        else {
            $orderType = $this->_quote_or_order->getMaxicycleOrderType();
        }
        
        if (isset($orderType)) {
            Mage::log('OrderType was already set! OrderType: ' . $orderType, null, 'maxicycle.log');
            return $orderType == 'treatment';
        }
        // calculate if treatment or control
        $treatment_group_size = $this->_getTreatmentGroupSize();
        return (rand(0, 100) <= $treatment_group_size ? true : false);
    }
    
        // Check if we insert for Order or Quote
    private function _isOrder() {
        return ($this->_quote_or_order instanceof Mage_Sales_Model_Order); 
    }
        
}
