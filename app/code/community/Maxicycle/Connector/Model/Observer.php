<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Model_Observer{

    var $_db = null;
    var $_dbResource = null;
    var $_now = '';
    var $_config = array();
    
    public function __construct() {
        $this->_db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->_dbResource = Mage::getSingleton('core/resource');
        $this->_now = date("Y-m-d H:i:s");      
    }
    
    private function setConfig($store_id) {
        $this->_config = Mage::helper('maxicycle')->getConfiguration($store_id);        
    }
    
    // manually triggered event for quote
    public function maxicycle_add_product_to_quote_hook($observer) {
        Mage::log('EXPORT: Event hook maxicycle_add_product_to_quote', null, 'maxicycle.log');
        try {
            $quote = $observer->getQuote();
            $this->add_product_to_quote($quote);
        }
        catch(Exception $e) {
          Mage::log('EXPORT: QUOTE: maxicycle_add_product_to_quote hook failed: ' .$e->getMessage(), null, 'maxicycle.log');
        }  
    }
    
    // triggered by event observer
    public function one_page_checkout_hook($observer) {
        Mage::log('### One page checkout start', null, 'maxicycle.log');
        try {
            $quote = $this->getQuote();
            $this->add_product_to_quote($quote);
        }
        catch(Exception $e) {
          Mage::log('QUOTE: one_page_checkout hook failed: ' .$e->getMessage(), null, 'maxicycle.log');
        }        
        Mage::log('### One page checkout end', null, 'maxicycle.log');
    }
        
    // will add product to quote table to have product ready for payment methods
    // like Klarna which sends the cart items before the actual order creation
    public function add_product_to_quote($quote) {        
        try {
            $store_id = $quote->getStoreId();
            $maxicycle = Mage::getModel('maxicycle/insert', array('store_id' => $store_id, 'quote_or_order' => $quote, 'is_type' => 'quote'));
            $this->setConfig($store_id);
            
            if ($this->checkoutEnabled() && $this->moduleEnabled()) {
                $maxicycle->addInsert();             
            } else {
                 Mage::log('Checkout adding disabled', null, 'maxicycle.log');
            }
        }
        catch(Exception $e) {
          Mage::log('Adding product to quote failed: ' .$e->getMessage(), null, 'maxicycle.log');
        }         
    }    
    
    // add PRODUCT after order placement
    // This needs to run for all payment methods that skip the checkout like 
    // Paypal express
    public function add_product_to_order($observer) {
        Mage::log('### Order observer start', null, 'maxicycle.log');
        try {
            $order = $observer->getOrder();
            $store_id = $order->getStoreId();
            $this->setConfig($store_id);
            $maxicycle = Mage::getModel('maxicycle/insert', array('store_id' => $store_id, 'quote_or_order' => $order, 'is_type' => 'order'));
            
            if ($this->moduleEnabled()) {
                $maxicycle->addInsert();
            } else {
                 Mage::log('module disabled', null, 'maxicycle.log');
            }
        }
        catch(Exception $e) {
          Mage::log('Order Place After Exception: ' .$e->getMessage(), null, 'maxicycle.log');
        }                   
        Mage::log('### Order observer end', null, 'maxicycle.log');
    }
        
    
    // Export to results table if right status was reached    
    public function sales_order_save_after($observer) {               
        try {
            $order = $observer->getOrder();
            $this->setConfig($order->getStoreId());
            Mage::log('EXPORT: after save hook for order:' .  $order->getEntityId(), null, 'maxicycle.log');
            //Mage::log(json_encode($this->_config), null, 'maxicycle.log');
            //Mage::log($this->_config['valid_statuses'], null, 'maxicycle.log');
            // Check if Maxicycle is active and a valid status
            if ($this->shouldExport($order)) {
                Mage::log('EXPORT: valid status: ' . $order->getStatus(), null, 'maxicycle.log');
                // Load order info
                Mage::log('EXPORT: Order ID ' . $order->getEntityId(), null, 'maxicycle.log');
                Mage::log('EXPORT: valid status: ' . $order->getStatus(), null, 'maxicycle.log');
                // Get all active campaigns
                // Mage::log('EXPORT: Current Store ' . $order->getStoreId(), null, 'maxicycle.log');
                // TODO: refactor this, order has campaign id already 
                $active_campaigns = $this->_db->fetchAll("SELECT * FROM " . $this->_dbResource->getTableName('maxicycle/campaigns') .
                        " WHERE campaign_start <= '$this->_now' AND response_time_end >= '$this->_now' AND store_id = " . $order->getStoreId());
                // If there are some active campaigns
                if (count($active_campaigns) > 0) {
                    Mage::log('EXPORT: active campaigns', null, 'maxicycle.log');
                    // Loop over active campaings
                    foreach ($active_campaigns as $campaign) {
                        $this->exportToResults($order, $campaign);
                    }
                } else {
                    Mage::log('EXPORT: No active campaigns', null, 'maxicycle.log');
                }
            } else {
                # do nothing
                Mage::log('EXPORT: module disabled or invalid status', null, 'maxicycle.log');                
            }
        }
        catch(Exception $e) {
          Mage::log('EXPORT: Order Save After Exception: ' .$e->getMessage(), null, 'maxicycle.log');
        }
    }
    
    private function exportToResults($order, $campaign) {
        Mage::log('EXPORT: Starting Export', null, 'maxicycle.log');
        $order_id = $order->getIncrementId();
        // check if already exported
        $previous_order_exists = $this->_db->fetchAll("SELECT order_id FROM " . 
                $this->_dbResource->getTableName('maxicycle/results') . 
                " WHERE campaign_id = " . $campaign['campaign_id'] . 
                " AND order_id = ". $order_id ." LIMIT 1");

        if (count($previous_order_exists) == 1) {
            Mage::log('EXPORT: Order already exported: ' . $order->getIncrementId(), null, 'maxicycle.log');
        } else {
            
            Mage::log('EXPORT: exporting status: ' . $order->getStatus(), null, 'maxicycle.log');
            
            $response_to_order_id = $this->getResponseOrderId($order, $campaign);
            
            // do not export orders that are in response period and are not a response to a previous order
            if ($this->_inResponsePeriod($campaign) && is_null($response_to_order_id) ) {
                 Mage::log('EXPORT: not exporting, no response order id found', null, 'maxicycle.log');
                 return;
            } else {
                Mage::log('EXPORT: Response to order id: ' . $response_to_order_id, null, 'maxicycle.log');
            }
            
            // We query the results table for the customer id to check if it is a response order
            // therefore we need to set it
            $maxicycle_customer_id = $order->getMaxicycleCustomerId();
            
            // calculate gross profit
            $gross_profit = $this->calculateGrossProfit($order);

            // Prepare data for Maxicycle results table
            $data = array(
                'campaign_id' => $campaign['campaign_id'],
                'order_id' => $order_id,
                'maxicycle_customer_id' => $maxicycle_customer_id,
                'created_at' => $order->getCreatedAt(),
                'grand_total' => $order->getGrandTotal(),
                'order_profit' => $gross_profit,
                'last_order_update_date' => $order->getCreatedAt(),
                'export_flag' => 0,
                'campaign_order_type' => $this->campaignOrderType($order, $campaign),
                'response_to_order_id' => $response_to_order_id,
                'sku' => $this->sku($order, $campaign)                   
            );

            // Save order data into Maxicycle orders table
            try {
                $result = Mage::getModel('maxicycle/results')->setData($data)->save();
                Mage::log('EXPORT: Result: ' . $result->getId(), null, 'maxicycle.log');
            } catch (Exception $e) {
                Mage::log('EXPORT: ERROR: ' . $e->getMessage(), null, 'maxicycle.log');
            }
        }
    }

    private function calculateGrossProfit($order) {
        
        // Recount gross_profit according to module configuration
        $order_items = $order->getAllVisibleItems();
        $use_tax = intval($this->_config['use_tax']);
        $product_costs_type = $this->_config['product_costs_type'];
        $product_costs_attribute = $this->_config['product_costs_attribute'];
        $product_costs_fixed = $this->_config['product_costs_fixed'];
        $avg_order_costs = floatval($this->_config['order_costs']);
        $grand_total = $order->getGrandTotal();            
        $item_costs = 0.00;

        // Loop over all order items
        foreach ($order_items as $item) {
            $item_price = floatval($item->getBasePrice());
            $item_quantity = floatval($item->getQtyOrdered());
            // Load product
            $product = Mage::getModel('catalog/product')->load($item->getProductId());

            // get product price from attribute
            if ($product_costs_type == '1') {
                // get product price from specified attribute
                $bp = floatval($product->getData($product_costs_attribute));                    
//                Mage::log('EXPORT: Gross profit: attribute', null, 'maxicycle.log');                
                if ($bp != 0) {
//                    Mage::log('EXPORT: Gross profit: attribute value: ' . $bp, null, 'maxicycle.log');
                    $item_costs += ($item_quantity * $bp);
                } else {
//                    Mage::log('EXPORT: Gross profit: attribute value empty ' . $bp, null, 'maxicycle.log');
                    $item_costs += ($item_quantity * $item_price);
                }
            } else {
//                Mage::log('EXPORT: Gross profit: fixed product price - percentage', null, 'maxicycle.log');
                if (floatval($product_costs_fixed) != 0) {
                    // deduct percentage
                    $fixed_percentage = floatval($product_costs_fixed) / 100.00;
                    $item_costs += ($item_quantity * $item_price * $fixed_percentage);
//                    Mage::log('EXPORT: Gross profit: item costs ' . $item_costs, null, 'maxicycle.log');
                } else {
                    $item_costs += ($item_quantity * $item_price);
                }
            }
        }

        // deduct specified average order costs
        $gross_profit = ($grand_total - floatval($avg_order_costs));
        //Mage::log('EXPORT: Gross profit: Grand total - avg order costs' . $gross_profit, null, 'maxicycle.log');
        // deduct tax 
        if ($use_tax) {
            Mage::log('EXPORT: Gross profit, deducting tax', null, 'maxicycle.log');
            $gross_profit -= floatval($order->getTaxAmount());
        }
        // deduct order item costs
        $gross_profit -= $item_costs;
        return $gross_profit;
    }
    
    private function shouldExport($order) {
        return $this->moduleEnabled() && $this->validStatus($order);
    }
    
    // requires config to be set
    private function validStatus($order) {
        $status = $order->getStatus();
        Mage::log('EXPORT: order status: ' . $status, null, 'maxicycle.log');
        $valid_status = in_array($order->getStatus(), $this->_config['valid_statuses']);
        Mage::log('EXPORT: has valid status: ' . $valid_status, null, 'maxicycle.log');
        return $valid_status;
    }
    
    // requires _config to be set
    private function moduleEnabled() {
        $enabled = intval($this->_config['is_enable']);
        return $enabled;
    }
        
    // requires _config to be set
    // checks if product should be added during checkout
    private function checkoutEnabled() {
        $enabled = intval($this->_config['add_during_checkout']);
        Mage::log('EXPORT: Checkout enabled true/false: ' . $enabled, null, 'maxicycle.log');
        return $enabled;
    }
    
    // get checkout from controller
    private function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }
    
    // get quote from checkout
    private function getQuote() {
        return $this->getCheckout()->getQuote();
    }
    
    // check if campaign is in response period
    private function _inResponsePeriod($campaign) {
        $campaign_time_end = strtotime($campaign['campaign_end']);
        return ($campaign_time_end <= time());
    }
    
    // get order type depending on if campaign is in response period
    private function campaignOrderType($order, $campaign) {
        return ($this->_inResponsePeriod($campaign)) ? new Zend_Db_Expr('NULL') : $order->getMaxicycleOrderType();        
    }
    
    // get sku type depending on if campaign is in response period
    private function sku($order, $campaign) {
        return ($this->_inResponsePeriod($campaign)) ? new Zend_Db_Expr('NULL') : $order->getMaxicycleSku(); 
    }
    
    // Check if order is a response order and get id
    // Is queried against the results table if there is already an order for that campaign and customer id
    private function getResponseOrderId($order, $campaign) {
        Mage::log('Checking if response order', null, 'maxicycle.log');
        $maxicycle_customer_id = $order->getMaxicycleCustomerId();
        $response_to_order_id = NULL;
        // Identify if it is response order
        $previous_order_exists = $this->_db->fetchAll("SELECT order_id FROM " . $this->_dbResource->getTableName('maxicycle/results') . 
                " WHERE campaign_id = " . $campaign['campaign_id'] . 
                " AND maxicycle_customer_id = $maxicycle_customer_id AND campaign_order_type IS NOT NULL ORDER BY created_at DESC LIMIT 1");
        if (count($previous_order_exists) == 1) {
            Mage::log('Response order!', null, 'maxicycle.log');
            $response_to_order_id = $previous_order_exists[0]['order_id'];            
        }
        return $response_to_order_id;
    }
}
