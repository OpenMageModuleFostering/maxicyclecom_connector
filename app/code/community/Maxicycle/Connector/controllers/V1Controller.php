<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_V1Controller extends Mage_Core_Controller_Front_Action {
    /* Set API key action */

    public function keyAction() {
        // Load POST data and encode them
        $data = json_decode(file_get_contents("php://input"), true);
        // Check if API key is already set
        if (trim(Mage::getStoreConfig('maxicycle/maxicycle_option/api_key', (int) $data['store_id'])) == '') {
            // Run key request with loaded data
            if (!array_key_exists('api_key', $data) || !array_key_exists('store_id', $data)) {
                // There is no api key in reuqest
                header("HTTP/1.1 " . 403);
                echo json_encode(array('message' => 'No API key provided'));
                die();
            } else {
                // Create config object
                $config_model = new Mage_Core_Model_Config();
                // Save key into config table
                $config_model->saveConfig('maxicycle/maxicycle_option/api_key', $data['api_key'], 'stores', $data['store_id']);
                # refresh magento configuration cache
                Mage::app()->getCacheInstance()->cleanType('config');
                header("HTTP/1.1 " . 200);
                echo json_encode(array('message' => 'API key set'));
                die();
            }
        } else {
            // API key had been already set -> Validate request API key with current key by API object initialization
            $api = Mage::getModel('maxicycle/api_rest', $this->getRequest());
            // Load POST data and encode them
            $data = json_decode(file_get_contents("php://input"), true);
            // Run key request with loaded data
            echo $api->key($data);
            die();
        }
    }

    /* Return array with all stores - No API key validation needed */

    public function storesAction() {
        $stores_array = array();
        // Loop over all stores
        foreach (Mage::app()->getStores() as $store) {
            $stores_array[] = array('id' => $store->getId(), 'name' => $store->getName());
        }
        header("HTTP/1.1 " . 200);
        echo json_encode($stores_array);
        die();
    }

    /* Return current module version */

    public function versionAction() {
        $version_array = array('version' => (string) Mage::getConfig()->getModuleConfig("Maxicycle_Connector")->version);
        header("HTTP/1.1 " . 200);
        echo json_encode($version_array);
        die();
    }

    /* Return array with all product SKUs */

    public function productsAction() {
        // Init API
        $api = Mage::getModel('maxicycle/api_rest', $this->getRequest());
        // Load POST data and encode them
        $data = json_decode(file_get_contents("php://input"), true);
        // Call request
        echo $api->products($data);
        die();
    }

    /* Return result from CRUD campaign operation */

    public function campaignsAction() {
        // Init API
        $api = Mage::getModel('maxicycle/api_rest', $this->getRequest());
        // Get store ID according to api_key
        $store_id = $api->getStoreId();
        // Load POST data and encode them
        $data = json_decode(file_get_contents("php://input"), true);
        // Run key request with loaded data
        echo $api->campaigns($data, $store_id);
        die();
    }

    // Return results according to campaign id
    
    public function resultsAction() {
        // Init API
        $api = Mage::getModel('maxicycle/api_rest', $this->getRequest());
        // Check that campaign_id belongs to correct store id -> according to api_key
        $store_id = $api->getStoreId();
        // Campaign ID
        $campaign_id = $this->getId();
        // Call API
        echo $api->results($campaign_id, $store_id);
    }

    // Re-create results for specific campaign
    
    public function regenerateAction() {
        // verify method or return
        $this->verifyMethod('GET');
        // Init API
        $api = Mage::getModel('maxicycle/api_rest', $this->getRequest());
        // Check that campaign_id belongs to correct store id -> according to api_key
        $store_id = $api->getStoreId();
        // Campaign ID
        $campaign_id = $this->getId();
        // Call API
        echo $api->regenerate($campaign_id, $store_id);
    }

    // Import sample data
    
    public function sampleAction() {
        $campaign_id = $this->getId();
        $path = Mage::getModuleDir('data', 'Maxicycle_Connector');
        $sql = file_get_contents($path . DS . 'data_sample.sql');
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $db->exec("DELETE FROM `maxicycle_results` WHERE `campaign_id` = $campaign_id;");
        $db->exec($sql);
        $db->exec("UPDATE `maxicycle_results` SET export_flag = 0 WHERE `campaign_id` = 999;");
        $db->exec("UPDATE `maxicycle_results` SET campaign_id = $campaign_id WHERE `campaign_id` = 999;");
        // Return OK
        Mage::log("Sample Import for campaign $campaign_id finished");
        header("HTTP/1.1 " . 200);
        echo json_encode(array('message' => 'Sample data imported'));
        die();
    }
    
    private function getId() {
       // Get params campaign_id from request
        $params = $this->getRequest()->getParams();
        // Check params - only one should be available
        foreach ($params as $key => $value) {
            // Set campaign id
            $campaign_id = (int) $key;
        }
        return $campaign_id;
    }
    
    private function verifyMethod($method) {
        $requestMethod = $this->getRequest()->getMethod();
        if ($method != $requestMethod) {
            header("HTTP/1.1 " . 405);
            die();        
        }
    }       
}
