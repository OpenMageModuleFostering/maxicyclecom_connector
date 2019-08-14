<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Model_Observer {

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
    
    // add PRODUCT after order placement
    public function sales_order_place_after($observer) {
        try {
            $order = $observer->getOrder();
            $this->setConfig($order->getStoreId());
            if (intval($this->_config['is_enable'])) {
                Mage::log('MAXICYCLE: adding product to order', null, 'maxicycle.log');
                $active_campaigns = $this->_db->fetchAll("SELECT * FROM " . $this->_dbResource->getTableName('maxicycle/campaigns') . " WHERE campaign_start <= '$this->_now' AND response_time_end >= '$this->_now' AND store_id = " . Mage::app()->getStore()->getId());
                // If there is some active campaign
                if (count($active_campaigns) > 0) {
                    Mage::log('MAXICYCLE: active campaigns, checking for product to add', null, 'maxicycle.log');
                    // Loop over active campaings
                    foreach ($active_campaigns as $campaign) {
                        // add product to Order
                        $this->addProductToOrder($order, $campaign);
                        // Set campaign_id to order
                        $order->setMaxicycleCampaignId($campaign['campaign_id']);
                        $maxicycle_customer_id = $this->setCustomerIdToOrder($order, $campaign);
                        // check and mark if order is response order
                        $response_to_order_id = $this->checkIfResponseOrder($order, $maxicycle_customer_id, $campaign);
                    }
                } else {
                     Mage::log('MAXICYCLE: No active campaigns, not adding a product', null, 'maxicycle.log');
                }
            } else {
                 Mage::log('MAXICYCLE: module disabled', null, 'maxicycle.log');
            }
        }
        catch(Exception $e) {
          Mage::log('Order Place After Exception: ' .$e->getMessage(), null, 'maxicycle.log');
        }
    }
    
    public function sales_order_save_after($observer) {
        try {
            $order = $observer->getOrder();
            $this->setConfig($order->getStoreId());
            Mage::log('MAXICYCLE: after save hook for order:' .  $order->getEntityId(), null, 'maxicycle.log');
            //Mage::log(json_encode($this->_config), null, 'maxicycle.log');
            //Mage::log($this->_config['valid_statuses'], null, 'maxicycle.log');
            // Check if Maxicycle is active and a valid status
            if ($this->shouldExport($order)) {
                Mage::log('MAXICYCLE: valid status: ' . $order->getStatus(), null, 'maxicycle.log');
                // Load order info
                Mage::log('MAXICYCLE: Order ID ' . $order->getEntityId(), null, 'maxicycle.log');
                Mage::log('MAXICYCLE: valid status: ' . $order->getStatus(), null, 'maxicycle.log');
                // Get all active campaigns
                // Mage::log('MAXICYCLE: Current Store ' . $order->getStoreId(), null, 'maxicycle.log');
                // TODO: refactor this, order has campaign id already 
                $active_campaigns = $this->_db->fetchAll("SELECT * FROM " . $this->_dbResource->getTableName('maxicycle/campaigns') .
                        " WHERE campaign_start <= '$this->_now' AND response_time_end >= '$this->_now' AND store_id = " . $order->getStoreId());
                // If there are some active campaigns
                if (count($active_campaigns) > 0) {
                    Mage::log('MAXICYCLE: active campaigns', null, 'maxicycle.log');
                    // Loop over active campaings
                    foreach ($active_campaigns as $campaign) {
                        $this->exportToResults($order, $campaign);
                    }
                } else {
                    Mage::log('MAXICYCLE: No active campaigns', null, 'maxicycle.log');
                }
            } else {
                # do nothing
                Mage::log('MAXICYCLE: module disabled or invalid status', null, 'maxicycle.log');                
            }
        }
        catch(Exception $e) {
          Mage::log('Order Save After Exception: ' .$e->getMessage(), null, 'maxicycle.log');
        }
    }

    public function testCondition($condition_code, $value, $order) {
        if ($condition_code == 'treatment_group_size') {
            // Generate random number
            $random_number = rand(0, 100);
            // Log random number for overview
            Mage::log('MAXICYCLE: Random number for order #' . $order->getIncrementId() . ' was: ' . $random_number, null, 'maxicycle.log');
            if ($random_number <= $value) {
                return true;
            } else {
                return false;
            }
        }
    }
    
    private function exportToResults($order, $campaign) {
        Mage::log('MAXICYCLE: Starting Export', null, 'maxicycle.log');
        $order_id = $order->getIncrementId();
        // check if already exported
        $previous_order_exists = $this->_db->fetchAll("SELECT order_id FROM " . 
                $this->_dbResource->getTableName('maxicycle/results') . 
                " WHERE campaign_id = " . $campaign['campaign_id'] . 
                " AND order_id = ". $order_id ." LIMIT 1");

        if (count($previous_order_exists) == 1) {
            Mage::log('MAXICYCLE: Order already exported: ' . $order->getIncrementId(), null, 'maxicycle.log');
        } else {
            
            Mage::log('MAXICYCLE: exporting status: ' . $order->getStatus(), null, 'maxicycle.log');
            
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
                Mage::log('MAXICYCLE: Result: ' . $result->getId(), null, 'maxicycle.log');
            } catch (Exception $e) {
                Mage::log('MAXICYCLE: ERROR: ' . $e->getMessage(), null, 'maxicycle.log');
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
        
        // If not then assign maxicycle customer is as order entity ID is, if yes then use maxicycle_customer_id from the past
        if (count($maxicycle_customer_id_exist) > 0) {
            $maxicycle_customer_id = intval($maxicycle_customer_id_exist[0]['maxicycle_customer_id']);
            Mage::log('MAXICYCLE: existing customer id found: ' . $maxicycle_customer_id, null, 'maxicycle.log');
        } else {
            $maxicycle_customer_id = $order->getEntityId();
            Mage::log('MAXICYCLE: new customer id: ' . $maxicycle_customer_id, null, 'maxicycle.log');
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
//                Mage::log('MAXICYCLE: Gross profit: attribute', null, 'maxicycle.log');                
                if ($bp != 0) {
//                    Mage::log('MAXICYCLE: Gross profit: attribute value: ' . $bp, null, 'maxicycle.log');
                    $item_costs += ($item_quantity * $bp);
                } else {
//                    Mage::log('MAXICYCLE: Gross profit: attribute value empty ' . $bp, null, 'maxicycle.log');
                    $item_costs += ($item_quantity * $item_price);
                }
            } else {
//                Mage::log('MAXICYCLE: Gross profit: fixed product price - percentage', null, 'maxicycle.log');
                if (floatval($product_costs_fixed) != 0) {
                    // deduct percentage
                    $fixed_percentage = floatval($product_costs_fixed) / 100.00;
                    $item_costs += ($item_quantity * $item_price * $fixed_percentage);
//                    Mage::log('MAXICYCLE: Gross profit: item costs ' . $item_costs, null, 'maxicycle.log');
                } else {
                    $item_costs += ($item_quantity * $item_price);
                }
            }
        }

        // deduct specified average order costs
        $gross_profit = ($grand_total - floatval($avg_order_costs));
        Mage::log('MAXICYCLE: Gross profit: Grand total - avg order costs' . $gross_profit, null, 'maxicycle.log');
        // deduct tax 
        if ($use_tax) {
            Mage::log('MAXICYCLE: Gross profit, deducting tax', null, 'maxicycle.log');
            $gross_profit -= floatval($order->getTaxAmount());
        }
        // deduct order item costs
        $gross_profit -= $item_costs;
        return $gross_profit;
    }
    
    private function addProductToOrder($order, $campaign) {
        Mage::log('MAXICYCLE: adding product', null, 'maxicycle.log');
        $campaign_time_end = strtotime($campaign['campaign_end']);
        // Process condition - ADD PRODUCT - only if campaign is still in CP and not already in RT
        if ($campaign_time_end >= time()) {
            $conditions = explode('|', $campaign['condition']);
            if (count($conditions) > 0) {
                // Loop over conditions
                for ($i = 0; $i < count($conditions); $i++) {
                    $condition = explode(":", $conditions[$i]);
                    if (count($condition) == 2) {
                        // Get code and value of condition
                        $condition_code = $condition[0];
                        $condition_value = $condition[1];

                        // Test condition
                        switch ($condition_code) {
                            case 'treatment_group_size' : {
                                    // if treatment condition true -> insert rule SKU
                                    if ($this->testCondition($condition_code, $condition_value, $order)) {
                                        Mage::log('MAXICYCLE: OrderType: treatment', null, 'maxicycle.log');
                                        // Load product
                                        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $campaign['product_sku']);
                                        if ($product) {
                                            // Save product into order
                                            $rowTotal = 0.00;
                                            $order_item = Mage::getModel('sales/order_item')->setStoreId(null)->setQuoteItemId(null)->setQuoteParentItemId(null)->setProductId($product->getId())->setProductType($product->getTypeId())->setQtyBackordered(null)->setTotalQtyOrdered(1)->setQtyOrdered(1)->setName($product->getName())->setSku($product->getSku())->setPrice(0)->setBasePrice(0)->setOriginalPrice(0)->setRowTotal($rowTotal)->setBaseRowTotal($rowTotal)->setOrder($order);
                                            $order_item->save();
                                            // Set it into order
                                            $order->setMaxicycleOrderType('treatment');                                            
                                        } else {
                                            Mage::log('MAXICYCLE: WARNING: Product ' . $campaign['product_sku'] . ' not found for control order', null, 'maxicycle.log');
                                        }
                                    } else {
                                        // Set it into order to be used when exporting the results
                                        $order->setMaxicycleOrderType('control');
                                        Mage::log('MAXICYCLE: OrderType: control', null, 'maxicycle.log');
                                    }
                                    break;
                                }
                        }
                    }
                }
            }
        } else {
            Mage::log('MAXICYCLE: Not adding product, campaign in response period', null, 'maxicycle.log');            
        }
    }
    
    private function checkIfResponseOrder($order, $maxicycle_customer_id, $campaign) {
        Mage::log('MAXICYCLE: campaign: ' . $campaign['campaign_id'], null, 'maxicycle.log');
        Mage::log('MAXICYCLE: customer id: ' . $maxicycle_customer_id, null, 'maxicycle.log');
        $response_to_order_id = NULL;
        // Identify if it is response order
        $previous_order_exists = $this->_db->fetchAll("SELECT order_id FROM " . $this->_dbResource->getTableName('maxicycle/results') . 
                " WHERE campaign_id = " . $campaign['campaign_id'] . 
                " AND maxicycle_customer_id = $maxicycle_customer_id AND campaign_order_type IS NOT NULL ORDER BY created_at DESC LIMIT 1");
        if (count($previous_order_exists) == 1) {
            Mage::log('MAXICYCLE: response order', null, 'maxicycle.log');
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
        Mage::log('MAXICYCLE: order status: ' . $status, null, 'maxicycle.log');
        $valid_status = in_array($order->getStatus(), $this->_config['valid_statuses']);
        Mage::log('MAXICYCLE: has valid status: ' . $valid_status, null, 'maxicycle.log');
        return $valid_status;
    }
    
    // requires _config to be set
    private function moduleEnabled() {
        $enabled = intval($this->_config['is_enable']);
        Mage::log('MAXICYCLE: Module enabled true/false: ' . $enabled, null, 'maxicycle.log');
        return $enabled;
    }
    
    // should have type set - fixes bug when exported before order_place_after_ran
    private function hasCampaignOrderTypeSet($order) {
        $order_type = $order->getMaxicycleOrderType();
        $has_type_set = isset($order_type);
        Mage::log('MAXICYCLE: Campaign order type is set true/false: ' . $has_type_set, null, 'maxicycle.log');
        return $has_type_set;
    }    
}
