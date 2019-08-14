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
    
    // get all active campaigns running or in response time
    private function getActiveCampaigns() {
        return $this->_db->fetchAll("SELECT * FROM " . $this->_dbResource->getTableName('maxicycle/campaigns') . " WHERE campaign_start <= '$this->_now' AND response_time_end >= '$this->_now' AND store_id = " . Mage::app()->getStore()->getId());
    }
    
    // manually triggered event for quote
    public function maxicycle_add_product_to_quote_hook($observer) {
        Mage::log('Event hook maxicycle_add_product_to_quote', null, 'maxicycle.log');
        try {
            $quote = $observer->getQuote();
            $this->add_product_to_quote($quote);
        }
        catch(Exception $e) {
          Mage::log('QUOTE: maxicycle_add_product_to_quote hook failed: ' .$e->getMessage(), null, 'maxicycle.log');
        }  
    }
    
    // triggered by event observer
    public function one_page_checkout_hook($observer) {
        Mage::log('controller_action_predispatch_checkout_onepage_index', null, 'maxicycle.log');
        try {
            $quote = $this->getQuote();
            $this->add_product_to_quote($quote);
        }
        catch(Exception $e) {
          Mage::log('QUOTE: one_page_checkout hook failed: ' .$e->getMessage(), null, 'maxicycle.log');
        }   
    }
        
    // will add product to quote table to have product ready for payment methods
    // like Klarna which sends the cart items before the actual order creation
    public function add_product_to_quote($quote) {
        try {
            $this->setConfig($quote->getStoreId());
            if ($this->moduleEnabled() && $this->checkoutEnabled() && !$this->hasCampaignOrderTypeSet($quote)) {
                Mage::log('QUOTE: adding product to order', null, 'maxicycle.log');
                $active_campaigns = $this->getActiveCampaigns();
                // If there is some active campaign
                if (count($active_campaigns) > 0) {
                    Mage::log('QUOTE: active campaigns, checking for product to add', null, 'maxicycle.log');
                    // Loop over active campaings
                    foreach ($active_campaigns as $campaign) {
                        // add product to Quote
                        $this->_addProductToCart($quote, $campaign); 
                        $quote->setMaxicycleCampaignId($campaign['campaign_id']);
                    }
                } else {
                     Mage::log('QUOTE: No active campaigns, not adding a product', null, 'maxicycle.log');
                }
            } else {
                 Mage::log('QUOTE: Checkout adding disabled or product already added', null, 'maxicycle.log');
            }
        }
        catch(Exception $e) {
          Mage::log('QUOTE: Adding product to quote failed: ' .$e->getMessage(), null, 'maxicycle.log');
        }         
    }    
    
    
    // add PRODUCT after order placement
    // This needs to run for all payment methods that skip the checkout like 
    // Paypal express
    public function add_product_to_order($observer) {
        Mage::log('adding product to order', null, 'maxicycle.log');
        try {
            $order = $observer->getOrder();
            $this->setConfig($order->getStoreId());
            if ($this->moduleEnabled()) {
                Mage::log('adding product to order', null, 'maxicycle.log');
                $active_campaigns = $this->getActiveCampaigns();
                // If there is some active campaign
                if (count($active_campaigns) > 0) {
                    Mage::log('ORDER: active campaigns, checking for product to add', null, 'maxicycle.log');
                    // Loop over active campaings
                    foreach ($active_campaigns as $campaign) {
                        // add product to Order
                        $this->_addProductToOrder($order, $campaign);
                        // Set campaign_id to order
                        $order->setMaxicycleCampaignId($campaign['campaign_id']);
                        $maxicycle_customer_id = $this->setCustomerIdToOrder($order, $campaign);
                        // check and mark if order is response order
                        $this->checkIfResponseOrder($order, $maxicycle_customer_id, $campaign);
                    }
                } else {
                     Mage::log('No active campaigns, not adding a product', null, 'maxicycle.log');
                }
            } else {
                 Mage::log('module disabled', null, 'maxicycle.log');
            }
        }
        catch(Exception $e) {
          Mage::log('Order Place After Exception: ' .$e->getMessage(), null, 'maxicycle.log');
        }                   
    }
        
    
    // Export to results table if right status was reached    
    public function sales_order_save_after($observer) {               
        try {
            $order = $observer->getOrder();
            $this->setConfig($order->getStoreId());
            Mage::log('after save hook for order:' .  $order->getEntityId(), null, 'maxicycle.log');
            //Mage::log(json_encode($this->_config), null, 'maxicycle.log');
            //Mage::log($this->_config['valid_statuses'], null, 'maxicycle.log');
            // Check if Maxicycle is active and a valid status
            if ($this->shouldExport($order)) {
                Mage::log('valid status: ' . $order->getStatus(), null, 'maxicycle.log');
                // Load order info
                Mage::log('Order ID ' . $order->getEntityId(), null, 'maxicycle.log');
                Mage::log('valid status: ' . $order->getStatus(), null, 'maxicycle.log');
                // Get all active campaigns
                // Mage::log('Current Store ' . $order->getStoreId(), null, 'maxicycle.log');
                // TODO: refactor this, order has campaign id already 
                $active_campaigns = $this->_db->fetchAll("SELECT * FROM " . $this->_dbResource->getTableName('maxicycle/campaigns') .
                        " WHERE campaign_start <= '$this->_now' AND response_time_end >= '$this->_now' AND store_id = " . $order->getStoreId());
                // If there are some active campaigns
                if (count($active_campaigns) > 0) {
                    Mage::log('active campaigns', null, 'maxicycle.log');
                    // Loop over active campaings
                    foreach ($active_campaigns as $campaign) {
                        $this->exportToResults($order, $campaign);
                    }
                } else {
                    Mage::log('No active campaigns', null, 'maxicycle.log');
                }
            } else {
                # do nothing
                Mage::log('module disabled or invalid status', null, 'maxicycle.log');                
            }
        }
        catch(Exception $e) {
          Mage::log('Order Save After Exception: ' .$e->getMessage(), null, 'maxicycle.log');
        }
    }
    
    private function _isTreatment($treatment_size) {
        // Generate random number
        $random_number = rand(0, 100);
        // Log random number for overview
        if ($random_number <= $treatment_size) {
            return true;
        } else {
            return false;
        }        
    }
    
    private function exportToResults($order, $campaign) {
        Mage::log('Starting Export', null, 'maxicycle.log');
        $order_id = $order->getIncrementId();
        // check if already exported
        $previous_order_exists = $this->_db->fetchAll("SELECT order_id FROM " . 
                $this->_dbResource->getTableName('maxicycle/results') . 
                " WHERE campaign_id = " . $campaign['campaign_id'] . 
                " AND order_id = ". $order_id ." LIMIT 1");

        if (count($previous_order_exists) == 1) {
            Mage::log('Order already exported: ' . $order->getIncrementId(), null, 'maxicycle.log');
        } else {
            
            Mage::log('exporting status: ' . $order->getStatus(), null, 'maxicycle.log');
            
            // no idea why we do this, but we check and set the customer id to the order
            $maxicycle_customer_id = $order->getMaxicycleCustomerId();
            //$maxicycle_customer_id = $order->getCustomerId();

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
                'campaign_order_type' => $order->getMaxicycleOrderType(),
                'response_to_order_id' => $order->getMaxicycleResponseToOrderId()
            );

            // Save order data into Maxicycle orders table
            try {
                $result = Mage::getModel('maxicycle/results')->setData($data)->save();
                Mage::log('Result: ' . $result->getId(), null, 'maxicycle.log');
            } catch (Exception $e) {
                Mage::log('ERROR: ' . $e->getMessage(), null, 'maxicycle.log');
            }
        }
    }

    private function setCustomerIdToOrder($order, $campaign) {
        // Customer email
        $customer_email = $order->getCustomerEmail();
        // Check if order exist with same customer email and already with Maxicycle Customer ID
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
//                Mage::log('Gross profit: attribute', null, 'maxicycle.log');                
                if ($bp != 0) {
//                    Mage::log('Gross profit: attribute value: ' . $bp, null, 'maxicycle.log');
                    $item_costs += ($item_quantity * $bp);
                } else {
//                    Mage::log('Gross profit: attribute value empty ' . $bp, null, 'maxicycle.log');
                    $item_costs += ($item_quantity * $item_price);
                }
            } else {
//                Mage::log('Gross profit: fixed product price - percentage', null, 'maxicycle.log');
                if (floatval($product_costs_fixed) != 0) {
                    // deduct percentage
                    $fixed_percentage = floatval($product_costs_fixed) / 100.00;
                    $item_costs += ($item_quantity * $item_price * $fixed_percentage);
//                    Mage::log('Gross profit: item costs ' . $item_costs, null, 'maxicycle.log');
                } else {
                    $item_costs += ($item_quantity * $item_price);
                }
            }
        }

        // deduct specified average order costs
        $gross_profit = ($grand_total - floatval($avg_order_costs));
        Mage::log('Gross profit: Grand total - avg order costs' . $gross_profit, null, 'maxicycle.log');
        // deduct tax 
        if ($use_tax) {
            Mage::log('Gross profit, deducting tax', null, 'maxicycle.log');
            $gross_profit -= floatval($order->getTaxAmount());
        }
        // deduct order item costs
        $gross_profit -= $item_costs;
        return $gross_profit;
    }
    
    private function _alreadyMaxicycleOrder($order) {
        Mage::log('Check if quote is already maxicycle order', null, 'maxicycle.log');
        $quote_id = $order->getQuoteId();
        $quote = Mage::getModel('sales/quote')->load($quote_id);
        $campaign_type_set = $this->hasCampaignOrderTypeSet($quote);
        // copy values
        if ($campaign_type_set) {                   
            $order->setMaxicycleOrderType($quote->getMaxicycleOrderType());
            $order->setMaxicycleCampaignId($quote->getMaxicycleCampaignId());
        }
        return $campaign_type_set;        
    }
    
    private function _addProductToOrder($order, $campaign) {        
        Mage::log('Place order -> check adding order', null, 'maxicycle.log');
         // exit if product was already added in quote               
        $already_added = $this->_alreadyMaxicycleOrder($order);
        if ($already_added) {
            Mage::log('Product was already added to order', null, 'maxicycle.log');
        } else {
            // Process condition - ADD PRODUCT - only if campaign is still in CP and not already in RT
            if ($this->_campaignActive($campaign)) {            
                // conditiond = treatment_group_size:90
                $condition = explode(":", $campaign['condition']);
                // Get code and value of condition
                $treatment_group_size = $condition[1];
                // Test condition
                // if treatment condition true -> insert SKU
                if ($this->_isTreatment($treatment_group_size)) {
                        Mage::log('Product was NOT already added to order', null, 'maxicycle.log');
                        $this->addOrderItem($order, $campaign);                                        
                    // Set it into order
                    $order->setMaxicycleOrderType('treatment');                                            
                    Mage::log('OrderType: treatment', null, 'maxicycle.log');                                    
                } else {
                    // Set it into order to be used when exporting the results
                    $order->setMaxicycleOrderType('control');
                    Mage::log('OrderType: control', null, 'maxicycle.log');
                }

            } else {
                Mage::log('Not adding product, campaign in response period', null, 'maxicycle.log');            
            }                
        }
    }
    
    private function checkIfSKUAlreadyAdded($items, $sku) {
        $added = FALSE;
        Foreach($items as $item){     
            if ($sku == $item->getSku()) {
                $added = TRUE;
            }
        }
        return $added;
    }
    
    // create and order item to product
    private function addOrderItem($order, $campaign) {
        Mage::log('Adding product to order', null, 'maxicycle.log');
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $campaign['product_sku']);
        if ($product) {
            // Save product into order
            $rowTotal = 0.00;
            $order_item = Mage::getModel('sales/order_item')
                    ->setStoreId($order->getStore()->getStoreId())
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
        } else {
            Mage::log('WARNING: Product ' . $campaign['product_sku'] . ' not found for control order', null, 'maxicycle.log');
        }
    }
    
    private function checkIfResponseOrder($order, $maxicycle_customer_id, $campaign) {
        Mage::log('campaign: ' . $campaign['campaign_id'], null, 'maxicycle.log');
        Mage::log('customer id: ' . $maxicycle_customer_id, null, 'maxicycle.log');
        $response_to_order_id = NULL;
        // Identify if it is response order
        $previous_order_exists = $this->_db->fetchAll("SELECT order_id FROM " . $this->_dbResource->getTableName('maxicycle/results') . 
                " WHERE campaign_id = " . $campaign['campaign_id'] . 
                " AND maxicycle_customer_id = $maxicycle_customer_id AND campaign_order_type IS NOT NULL ORDER BY created_at DESC LIMIT 1");
        if (count($previous_order_exists) == 1) {
            Mage::log('response order', null, 'maxicycle.log');
            $response_to_order_id = $previous_order_exists[0]['order_id'];
            // Set it into order
            $order->setMaxicycleResponseToOrderId($response_to_order_id);
        }
        return $response_to_order_id;
    }
    
    private function shouldExport($order) {
        $this->hasCampaignOrderTypeSet($order);
        return $this->moduleEnabled() && $this->validStatus($order) && $this->hasCampaignOrderTypeSet($order);
    }
    
    // requires config to be set
    private function validStatus($order) {
        $status = $order->getStatus();
        Mage::log('order status: ' . $status, null, 'maxicycle.log');
        $valid_status = in_array($order->getStatus(), $this->_config['valid_statuses']);
        Mage::log('has valid status: ' . $valid_status, null, 'maxicycle.log');
        return $valid_status;
    }
    
    // requires _config to be set
    private function moduleEnabled() {
        $enabled = intval($this->_config['is_enable']);
        Mage::log('Module enabled true/false: ' . $enabled, null, 'maxicycle.log');
        return $enabled;
    }
        
    // requires _config to be set
    // checks if product should be added during checkout
    private function checkoutEnabled() {
        $enabled = intval($this->_config['add_during_checkout']);
        Mage::log('Checkout enabled true/false: ' . $enabled, null, 'maxicycle.log');
        return $enabled;
    }
    
    // should have type set - fixes bug when exported before order_place_after_ran
    private function hasCampaignOrderTypeSet($quote) {
        $order_type = $quote->getMaxicycleOrderType();
        $has_type_set = isset($order_type);
        Mage::log('Campaign order type is set true/false: ' . $has_type_set, null, 'maxicycle.log');
        return $has_type_set;
    }    
            
    private function _campaignActive($campaign) {
        $campaign_time_end = strtotime($campaign['campaign_end']);
        // condition is treatment group size > 0
        return ($campaign_time_end >= time());
    }

    protected function _addProductToCart($quote, $campaign) {
        Mage::log('Checkout: adding order', null, 'maxicycle.log');
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $campaign['product_sku']);
        // Process condition - ADD PRODUCT - only if campaign is still in CP and not already in RT
        if ($this->_campaignActive($campaign)) {            
            // conditiond = treatment_group_size:90
            $condition = explode(":", $campaign['condition']);
            // Get code and value of condition
            $treatment_group_size = $condition[1];
            // Test condition
            // if treatment condition true -> insert SKU
            if ($this->_isTreatment($treatment_group_size)) {
                // exit if product was already added in quote
               $already_added = $this->checkIfSKUAlreadyAdded($quote->getAllItems(), $campaign['product_sku']);
                if ($already_added) {
                    Mage::log('Product was already added to order', null, 'maxicycle.log');
                } else {
                    Mage::log('Product was NOT already added to order', null, 'maxicycle.log');
                   $this->_addQuoteItem($product, $quote);                                
                }
                // Set it into order
                $quote->setMaxicycleOrderType('treatment');                                            
                Mage::log('OrderType: treatment', null, 'maxicycle.log');                                    
            } else {
                // Set it into order to be used when exporting the results
                $quote->setMaxicycleOrderType('control');
                Mage::log('OrderType: control', null, 'maxicycle.log');
            }
        
        } else {
            Mage::log('Not adding product, campaign in response period', null, 'maxicycle.log');            
        }
                
    }   
    
    protected function _addQuoteItem($product, $quote) {
        if ($product->getId()) {
            try {
                // Save product into quote
                $rowTotal = 0.00;
                $order_item = Mage::getModel('sales/quote_item')
                ->setStoreId($quote->getStore()->getStoreId())
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
                Mage::log('Saving product to quote', null, 'maxicycle.log');
                
            } catch (Exception $e) {
                Mage::log('Adding product to quote failed: ' .$e->getMessage(), null, 'maxicycle.log');
                throw $e;
            }    
            return true;
        }
    }
        
    // get checkout from controller
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    
    // get quote from checkout
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
}
