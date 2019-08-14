<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Model_Api_Rest extends Maxicycle_Connector_Model_Api_Abstract {

    var $_db = null;
    var $_resource = null;
    var $_debug = true;

    public function __construct($request) {
        // Init abstract class
        parent::__construct($request);

        // Init DB connection
        $this->_db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->_resource = Mage::getSingleton('core/resource');
    }

    public function products($data) {
        if ($this->method == 'POST') {

            // Check if API key param exist between data
            if (!array_key_exists('sku', $data)) {
                // There is no sku in reuqest
                return $this->_response(array('message' => 'No SKU provided'), 403);
            }
            
            $missing_skus = $this->checkSkus($data['sku']);
            if (!empty($missing_skus)) {
                return $this->_response(array('message' => 'Product with SKU: ' . join(', ', $missing_skus) . ' does not exist'), 404);
            } else {
                return $this->_response(array('message' => 'Product with SKU: exist'), 200);
            }
            
        } else {
            // Default answer for all other HTTP methods
            return $this->_response(array('message' => 'Method: ' . $this->method . ' is not supported.'), 500);
        }
    }

    public function stores() {
        if ($this->method == 'GET') {
            // Result array
            $stores_array = array();
            // Loop over all stores
            foreach (Mage::app()->getStores() as $store) {
                $stores_array[] = array('id' => $store->getId(), 'name' => $store->getName());
            }
            // Check if there is any store
            if (count($stores_array) > 0) {
                // Return HTTP 200
                return $this->_response($stores_array, 200);
            } else {
                // There is no store at all
                return $this->_response(array('message' => 'There is no store'), 500);
            }
        } else {
            // Default answer for all other HTTP methods
            return $this->_response(array('message' => 'Method: ' . $this->method . ' is not supported.'), 500);
        }
    }

    public function key($data) {
        if ($this->method == 'POST') {

            // Check if API key param exist between data
            if (!array_key_exists('api_key', $data) || !array_key_exists('store_id', $data)) {
                // There is no api key in reuqest
                return $this->_response(array('message' => 'No API key or Store ID provided'), 403);
            } else {
                // Create config object
                $config_model = new Mage_Core_Model_Config();
                // Save key into config table
                $config_model->saveConfig('maxicycle/maxicycle_option/api_key', $data['api_key'], 'stores', $data['store_id']);
                // Return HTTP 200
                return $this->_response(array('message' => 'API key set'), 200);
            }
        } else {
            // Default answer for all other HTTP methods
            return $this->_response(array('message' => 'Method: ' . $this->method . ' is not supported.'), 500);
        }
    }

    public function campaigns($data, $store_id) {
        // Check HTTP METHOD
        if ($this->method == 'POST') {
            // Check if all params exist
            if (array_key_exists('name', $data) && array_key_exists('sku', $data) && array_key_exists('campaign_start', $data) && array_key_exists('campaign_end', $data) && array_key_exists('response_enddate', $data) && array_key_exists('treatment_group_size', $data)) {
                $missing_skus = $this->checkSkus($data['sku']);
                if (!empty($missing_skus)) {
                    return $this->_response(array('message' => 'Product with SKU: ' . join(', ', $missing_skus) . ' does not exist'), 404);
                }

                // Check if store with given ID exist
                $_store = Mage::getModel('core/store')->load($store_id);
                if (!$_store->getId()) {
                    return $this->_response(array('message' => 'Store with ID: ' . $store_id . ' not exist'), 404);
                }

                // Create campaign object
                $model = Mage::getModel('maxicycle/campaigns')->setName($data['name'])
                        ->setProductSku($data['sku'])
                        ->setCampaignStart(date("Y-m-d 00:00:00", strtotime($data['campaign_start'])))
                        ->setCampaignEnd(date("Y-m-d 23:59:59", strtotime($data['campaign_end'])))
                        ->setResponseTimeEnd(date("Y-m-d 23:59:59", strtotime($data['response_enddate'])))
                        ->setStoreId($store_id)
                        ->setCondition('treatment_group_size:' . intval($data['treatment_group_size']));

                // Save campaign
                try {
                    $model->save();
                    // Send output
                    return $this->_response(array('message' => 'Created', 'campaign_id' => $model->getId()), 201);
                } catch (Exception $ex) {
                    // Catch and send exception to output                    
                    return $this->_response(array('message' => 'Error during campaign save: ' . $ex->getMessage()), 500);
                }
            } else {
                // Some of param(s) is missing
                return $this->_response(array('message' => 'Some of required param(s) is missing, please check your request. Required params: name, sku, campaign_start, campaign_end, response_enddate, treatment_group_size'), 500);
            }
        } else if ($this->method == 'PUT') {
            // Params are in file variable
            $data = json_decode($this->file, true);
            // Check if all params exist
            if (array_key_exists('campaign_id', $data) && array_key_exists('name', $data) && array_key_exists('sku', $data) && array_key_exists('campaign_start', $data) && array_key_exists('campaign_end', $data) && array_key_exists('response_enddate', $data) && array_key_exists('treatment_group_size', $data)) {
                // Check if campaign with given ID exist
                $model = Mage::getModel("maxicycle/campaigns")->load(intval($data['campaign_id']));
                if (!$model->getId()) {
                    return $this->_response(array('message' => 'Campaign with ID: ' . $data['campaign_id'] . ' not exist'), 404);
                }

                $missing_skus = $this->checkSkus($data['sku']);
                if (!empty($missing_skus)) {
                    return $this->_response(array('message' => 'Product with SKU: ' . join(', ', $missing_skus) . ' does not exist'), 404);
                }

                // Check if store with given ID exist
                $_store = Mage::getModel('core/store')->load($store_id);
                if (!$_store->getId()) {
                    return $this->_response(array('message' => 'Store with ID: ' . $store_id . ' not exist'), 404);
                }

                // Update campaign object
                $model->setName($data['name'])
                        ->setProductSku($data['sku'])
                        ->setCampaignStart(date("Y-m-d 00:00:00", strtotime($data['campaign_start'])))
                        ->setCampaignEnd(date("Y-m-d 23:59:59", strtotime($data['campaign_end'])))
                        ->setResponseTimeEnd(date("Y-m-d 23:59:59", strtotime($data['response_enddate'])))
                        ->setStoreId($store_id)
                        ->setCondition('treatment_group_size:' . intval($data['treatment_group_size']));

                // Save campaign
                try {
                    $model->save();
                    // Send output
                    return $this->_response(array('message' => 'Updated', 'campaign_id' => $model->getId()), 204);
                } catch (Exception $ex) {
                    // Catch and send exception to output                    
                    return $this->_response(array('message' => 'Error during campaign update: ' . $ex->getMessage()), 500);
                }
            } else {
                // Some of param(s) is missing
                return $this->_response(array('message' => 'Some of required param(s) is missing, please check your request. Required params: campaign_id, name, sku, store_id, campaign_start, campaign_end, response_enddate, treatment_group_size'), 500);
            }
        } else if ($this->method == 'DELETE') {
            // Check if all params exist
            if (array_key_exists('campaign_id', $data)) {
                // Check if campaign with given ID exist
                $model = Mage::getModel("maxicycle/campaigns")->load((int) $data['campaign_id']);
                if (!$model->getId()) {
                    return $this->_response(array('message' => 'Campaign with ID: ' . $data['campaign_id'] . ' not exist'), 404);
                }

                // Save campaign
                try {
                    $model->delete();
                    // Send output
                    return $this->_response(array('message' => 'Removed', 'campaign_id' => $model->getId()), 200);
                } catch (Exception $ex) {
                    // Catch and send exception to output                    
                    return $this->_response(array('message' => 'Error during campaign delete: ' . $ex->getMessage()), 500);
                }
            } else {
                // Some of param(s) is missing
                return $this->_response(array('message' => 'Some of required param(s) is missing, please check your request. Required params: campaign_id'), 500);
            }
        } else {
            // Default answer for all other HTTP methods
            return $this->_response(array('message' => 'Method: ' . $this->method . ' is not supported.'), 500);
        }
    }
    
    // Check skus if they exist in the shop
    // sku_string 'sku1:30;sku2:40;sku3:30'        
    private function checkSkus($sku_string) {
        $skus = explode(';', $sku_string); 
        // $skus == ['sku1:30', 'sku2:40', 'sku3:30'] 
        $not_found_sku = array();
        
        foreach($skus as $sku_percentage) {
            Mage::log('Checking sku percentage: ' . $sku_percentage , null, 'maxicycle.log');
            $sku_p_ary = explode(':', $sku_percentage);
            $sku = $sku_p_ary[0];            
            // if not found
            if (!$this->checkProduct($sku)) {
              Mage::log('Sku not found: ' . $sku , null, 'maxicycle.log');
              array_push($not_found_sku, $sku);
            }
        }
        return $not_found_sku;
    }
    
    private function checkProduct($sku) {
      $_product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
      return ($_product ? true : false );
    }

    public function results($campaign_id, $store_id) {
        if ($this->method == 'GET') {
            if ($campaign_id != 0) {
                // Check if campaign with given ID exist
                $model = Mage::getModel("maxicycle/campaigns")->load($campaign_id);
                if (!$model->getId()) {
                    return $this->_response(array('message' => 'Campaign with ID: ' . $campaign_id . ' not exist'), 404);
                }
                // Check if campaign with given ID belongs to correct store_id
                if ($model->getStoreId() != $store_id) {
                    return $this->_response(array('message' => 'Campaign with ID: ' . $campaign_id . ' exist but given api_key is associate with different store id - ' . $store_id . ' - ' . $model->getStoreId()), 404);
                }

                // Order statuses which could be export to Rails App
                $enable_statuses = explode(',', Mage::getStoreConfig('maxicycle/maxicycle_option/valid_statuses'));

                // Results array
                $results = array();

                // Load all campaigns
                $orders = Mage::getModel('maxicycle/results')->getCollection()->addFieldToFilter('campaign_id', $campaign_id)->addFieldToFilter('export_flag', 0);
                // Loop over all campaigns
                foreach ($orders as $order) {
                    // For debug purpose
                    if ($this->_debug) {
                        // Simply return data without any check
                        $results[] = array(
                            'campaign_id' => $order->getCampaignId(),
                            'order_id' => $order->getOrderId(),
                            'customer_id' => $order->getMaxicycleCustomerId(),
                            'campaign_order_type' => $order->getCampaignOrderType(),
                            'order_date' => $order->getCreatedAt(),
                            'response_to_order_id' => $order->getResponseToOrderId(),
                            'revenue' => $order->getGrandTotal(),
                            'gross_profit' => $order->getOrderProfit(),
                            'last_order_update' => $order->getLastOrderUpdateDate(),
                            'sku' => $order->getSku()
                        );
                        $order->setExportFlag(1)->save();
                    } else {
                        // Order status check
                        $order_status = $this->_db->fetchOne("SELECT status FROM " . $this->_resource->getTableName("sales_flat_order_grid") . " WHERE increment_id = '" . $order->getOrderId() . "'");

                        if (in_array($order_status, $enable_statuses)) {
                            $results[] = array(
                                'campaign_id' => $order->getCampaignId(),
                                'order_id' => $order->getOrderId(),
                                'customer_id' => $order->getMaxicycleCustomerId(),
                                'campaign_order_type' => $order->getCampaignOrderType(),
                                'order_date' => $order->getCreatedAt(),
                                'response_to_order_id' => $order->getResponseToOrderId(),
                                'revenue' => $order->getGrandTotal(),
                                'gross_profit' => $order->getOrderProfit(),
                                'last_order_update' => $order->getLastOrderUpdateDate(),
                                'sku' => $order->getSku()
                            );
                            $order->setExportFlag(1)->save();
                        }
                    }
                }
                // Check if there is any result
                if (count($results) > 0) {
                    // Return HTTP 200
                    return $this->_response($results, 200);
                } else {
                    // There is no store at all
                    return $this->_response(array('message' => 'There is no result'), 500);
                }
            } else {
                return $this->_response(array('message' => 'Campaign with ID: ' . $campaign_id . ' is wrong, please check request.'), 500);
            }
        } else {
            // Default answer for all other HTTP methods
            return $this->_response(array('message' => 'Method: ' . $this->method . ' is not supported.'), 500);
        }
    }

    public function regenerate($campaign_id, $store_id) {
        if ($this->method == 'GET') {
            if ($campaign_id != 0) {
                // Check if campaign with given ID exist
                $campaign = Mage::getModel("maxicycle/campaigns")->load($campaign_id);
                
                if (!$campaign->getId()) {
                    return $this->_response(array('message' => 'Campaign with ID: ' . $campaign_id . ' not exist'), 404);
                }
                // Check if campaign with given ID belongs to correct store_id
                if ($campaign->getStoreId() != $store_id) {
                    return $this->_response(array('message' => 'Campaign with ID: ' . $campaign_id . ' exist but given api_key is associate with different store id - ' . $store_id . ' - ' . $model->getStoreId()), 404);
                }

                if ($campaign) {
                    // DB connection
                    $db = Mage::getSingleton('core/resource')->getConnection('core_write');
                    $resource = Mage::getSingleton('core/resource');
                    $config = Mage::helper('maxicycle')->getConfiguration($campaign->getStoreId());
                    // Delete old results
                    $db->query("DELETE FROM " . $resource->getTableName('maxicycle/results') . " WHERE campaign_id = $campaign_id");
                    // Get orders which should be in results according to date
                    $orders = $db->query("SELECT entity_id FROM " . $resource->getTableName('sales_flat_order') . " WHERE maxicycle_campaign_id = $campaign_id AND created_at >= '" . $campaign->getCampaignStart() . "' AND created_at <= '" . $campaign->getResponseTimeEnd() . "'");
                    foreach ($orders as $o) {
                        // Load order object
                        $order = Mage::getModel('sales/order')->load((int) $o['entity_id']);
                        // Gross profit
                        $gross_profit = 0.0000;
                        // Recount gross_profit according to module configuration
                        $order_items = $order->getAllVisibleItems();
                        $use_tax = intval($config['use_tax']);
                        $product_costs_type = $config['product_costs_type'];
                        $product_costs_attribute = $config['product_costs_attribute'];
                        $product_costs_fixed = $config['product_costs_fixed'];
                        $order_costs = floatval($config['order_costs']);

                        // Loop over all order items
                        foreach ($order_items as $item) {
                            // Load product
                            $product = Mage::getModel('catalog/product')->load($item->getProductId());
                            // Check if use product price with or without tax
                            if (!$use_tax) {
                                // With
                                if ($product_costs_type == '1') {
                                    // Attribute
                                    if (trim($product_costs_attribute) != '') {
                                        // Decrease product price by buy price
                                        $bp = floatval($product->getData($product_costs_attribute));
                                        $gross_profit += (floatval($item->getQtyOrdered()) * (floatval($item->getBasePriceInclTax()) - $bp));
                                    } else {
                                        // Use original product price
                                        $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePriceInclTax()));
                                    }
                                } else {
                                    // Fixed
                                    if (floatval($product_costs_fixed) != 0) {
                                        // Decrease product price by percentage
                                        $fixed = 1 + floatval($product_costs_fixed) / 100.00;
                                        $gross_profit += (floatval($item->getQtyOrdered()) * (floatval($item->getBasePriceInclTax()) / $fixed));
                                    } else {
                                        // Use original product price
                                        $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePriceInclTax()));
                                    }
                                }
                            } else {
                                // Without
                                if ($product_costs_type == '1') {
                                    // Attribute
                                    if (trim($product_costs_attribute) != '') {
                                        // Decrease product price by buy price
                                        $bp = floatval($product->getData($product_costs_attribute));
                                        $gross_profit += (floatval($item->getQtyOrdered()) * (floatval($item->getBasePrice() - $bp)));
                                    } else {
                                        // Use original product price
                                        $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePrice()));
                                    }
                                } else {
                                    // Fixed
                                    if (floatval($product_costs_fixed) != 0) {
                                        // Decrease product price by percentage
                                        $fixed = 1 + floatval($product_costs_fixed) / 100.00;
                                        $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePrice() / $fixed));
                                    } else {
                                        // Use original product price
                                        $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePrice()));
                                    }
                                }
                            }
                        }

                        // Decrease order costs
                        $gross_profit -= floatval($order_costs);

                        // Prepare data for Maxicycle results table
                        $data = array(
                            'campaign_id' => $campaign_id,
                            'order_id' => $order->getIncrementId(),
                            'maxicycle_customer_id' => $order->getMaxicycleCustomerId(),
                            'created_at' => $order->getCreatedAt(),
                            'response_to_order_id' => $order->getMaxicycleResponseToOrderId(),
                            'grand_total' => $order->getGrandTotal(),
                            'order_profit' => $gross_profit,
                            'last_order_update_date' => $order->getUpdatedAt(),
                            'campaign_order_type' => $order->getMaxicycleOrderType(),
                            'sku' => $campaign->getSku(),
                            'export_flag' => 0
                        );

                        // Save into result table
                        Mage::getModel('maxicycle/results')->setData($data)->save();
                    }
                    return $this->_response(array('message' => 'Results had been generated properly'), 200);
                } else {
                    return $this->_response(array('message' => 'Campaign with ID: ' . $campaign_id . ' is wrong, please check request.'), 500);
                }
            } else {
                return $this->_response(array('message' => 'Campaign with ID: ' . $campaign_id . ' is wrong, please check request.'), 500);
            }
        } else {
            // Default answer for all other HTTP methods
            return $this->_response(array('message' => 'Method: ' . $this->method . ' is not supported.'), 500);
        }
    }

}
