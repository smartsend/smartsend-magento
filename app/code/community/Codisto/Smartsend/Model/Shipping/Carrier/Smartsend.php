<?php
class Codisto_Smartsend_Model_Shipping_Carrier_Smartsend
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    const CODE = 'smartsend';
    const AUSTRALIA_COUNTRY_CODE = 'AU';

    protected $_code = self::CODE;
    protected $_request = null;
    protected $_result = null;
    protected $_errors = array();
    protected $_defaultGatewayURL = 'https://api.smartsend.com.au/';

    protected function _createMethod($method, $title, $price, $cost)
    {
        $newMethod = Mage::getModel('shipping/rate_result_method');
        $newMethod->setCarrier($this->_code);
        $newMethod->setCarrierTitle($this->getConfigData('title'));
        $newMethod->setMethod($method);
        $newMethod->setMethodTitle($title);
        $newMethod->setPrice($this->getFinalPriceWithHandlingFee($price));
        $newMethod->setCost($cost);

        return $newMethod;
    }
    
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        // Check if this method is active
        if (!$this->getConfigFlag('active'))
        {
            Mage::Log('Shipping method ' . $this->_code . ' is not active, not collecting Rates');
            return false;
        }
        
        $this->_request = $request;
        $this->_result = Mage::getModel('shipping/rate_result');
        
        // TODO: Call out to freight gateway with package items to box pack and get quote results
        // Request quotes from gateway API
        $quotes = $this->_getQuotes();

        if ($quotes == false) // No quotes returned to match return current rate results.
            return $this->_result;

        foreach ($quotes as $quote)
        {
            $method = 'method';
            $title = 'title';
            $price = 100;
            $cost = 100;
            $newMethod = $this->_createMethod($method, $title, $price, $cost);
            $this->_result->append($newMethod);
        }

        return $this->_result;
    }

    protected function _getQuotes()
    {
        // Check if this is applicable, only allowing shipping from Australia.
        $origCountry = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
        if ($origCountry != self::AUSTRALIA_COUNTRY_CODE)
            return false;
        
        if ($this->_request->getDestCountryId())
            $destCountry = $this->_request->getDestCountryId();
        else
            $destCountry = self::AUSTRALIA_COUNTRY_CODE;
            
        // Smartsend only ships within Australia
        if ($destCountry != self::AUSTRALIA_COUNTRY_CODE)
            return false;

        // Fill out initial Smartsend API GetQuote request parameters
        $apiRequestParams = array(
                                'METHOD' => 'GETQUOTE',
                                'FROMCOUNTRYCODE' => $origCountry,
                                'FROMPOSTCODE' => Mage::getStoreConfig('shipping/origin/postcode', $this->getStore()),
                                'FROMSUBURB' => Mage::getStoreConfig('shipping/origin/city', $this->getStore()),
                                'TOCOUNTRYCODE' => $destCountry,
                                'TOPOSTCODE' => $this->_request->getDestPostcode(),
                                'TOSUBURB' => $this->_request->getDestCity(),
                                'SERVICETYPE' => 'ALL',
                                'RECEIPTEDDELIVERY' => 'No',
                                'TRANSPORTASSURANCE' => '0',
                                'TAILLIFT' => 'None',
                                'ITEMS' => array(),
                                /*
                                'ITEMS' => array(
                                                array(
                                                    'WEIGHT' => 'required in kgs',
                                                    'WIDTH' => 'required in cm',
                                                    'LENGTH' => 'required in cm',
                                                    'DEPTH' => 'required in cm',
                                                    'DESCRIPTION' => 'None',
                                                ),
                                            ),
                                */
                                'BOXES' => array(),
                                /*
                                'BOXES' => array(
                                                array(
                                                    'MAXWEIGHT' => 'required in kgs',
                                                    'WIDTH' => 'required in cm',
                                                    'LENGTH' => 'required in cm',
                                                    'DEPTH' => 'required in cm',
                                            ),
                                */
                                'USERCODE' => 'optional corporate client code',
                            );

        // Fill in item entries
        foreach ($this->_request->getAllItems() as $item)
        {
            if($item->getProduct()->isVirtual() || $item->getParentItem())
            {
                Mage::Log("Product is virtual or has parent Item, skipping. (file: " . __FILE__ . ", ln: " .  __LINE__ . ")");
                continue;
            }

            // TODO: Check for free shipping on products and don't count them in the items to query for SmartSend quote
            $productId = $item->getProduct()->getId();
            $itemProperties = array(
                                    'WEIGHT' => 1,
                                    'WIDTH' => 1,
                                    'LENGTH' => 1,
                                    'DEPTH' => 1,
                                    'DESCRIPTION' => 'CARTON',
                                );
            $apiRequestParams['ITEMS'][] = $itemProperties;
        }
        
        $apiRequestString = "";
        foreach ($apiRequestParams as $paramKey => $paramValue)
        {
            if($paramKey == "ITEMS" || $paramKey == "BOXES")
            {
                for($i = 0; $i < count($paramValue); $i++)
                {
                    $apiRequestString .= rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_WEIGHT") . "=" . rawurlencode($paramValue[$i]['WEIGHT']) . "&"
                                        . rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_WIDTH") . "=" . rawurlencode($paramValue[$i]['WIDTH']) . "&"
                                        . rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_LENGTH") . "=" . rawurlencode($paramValue[$i]['LENGTH']) . "&"
                                        . rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_DEPTH") . "=" . rawurlencode($paramValue[$i]['DEPTH']) . "&"
                                        . rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_DESCRIPTION") . "=" . rawurlencode($paramValue[$i]['DESCRIPTION']) . "&";
                }
            }
            else
                $apiRequestString .= rawurlencode($paramKey) . "=" . rawurlencode($paramValue) . "&";
        }
        Mage::Log("API Request String is '" . $apiRequestString . "'");

        // Perform Smartsend API request
        $apiRequest = curl_init();
        if($apiRequest == false)
        {
            Mage::Log("Failed to initialise cURL to perform smartsend API request");
            return false;
        }
        else
        {
            curl_setopt_array($apiRequest, array(
                                                CURLOPT_URL => $this->_defaultGatewayURL,
                                                CURLOPT_HEADER => 0,
                                                CURLOPT_RETURNTRANSFER => 1,
                                                CURLOPT_POSTFIELDS => $apiRequestString,
                                                CURLOPT_SSL_VERIFYPEER => false,
                                            ));
            $apiResponse = curl_exec($apiRequest);
            $quotes = false;
            if($apiResponse != false)
            {
                $quotes = array();
                Mage::Log("API Request Response: " . var_export($apiResponse, true));
                parse_str($apiResponse, $apiResult);
                Mage::Log("API Result Array: " . var_export($apiResult, true));
            }
            curl_close ($apiRequest);
            return $quotes;
        }
    }

    public function getCode($type, $code = '')
    {
        static $codes;
        $codes = array(
            'tailliftbooking' => array(
                'None' => Mage::helper('smartsend')->__('None'),
                'AtPickup' => Mage::helper('smartsend')->__('At Pickup'),
                'AtDestination' => Mage::helper('smartsend')->__('At Destination'),
                'Both' => Mage::helper('smartsend')->__('Both'),
            ),
            'servicetype' => array(
                'all' => Mage::helper('smartsend')->__('All'),
                'satchel' => Mage::helper('smartsend')->__('Satchel'),
                'road' => Mage::helper('smartsend')->__('Road'),
                'express' => Mage::helper('smartsend')->__('Express'),
            ),
        );

        if (!isset($codes[$type])) {
            return false;
        } elseif ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            return false;
        } else {
            return $codes[$type][$code];
        }
    }
    
    public function getAllowedMethods()
    {
        return null;
        
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = array();
        foreach ($allowed as $k)
            $arr[$k] = $this->getCode('service', $k);
        
        return $arr;
    }
    
    public function isCityRequired()
    {
        return true;
    }
    
    public function isZipCodeRequired()
    {
        return true;
    }
    
    public function isStateProvinceRequired()
    {
        return true;
    }
    
    public function isShippingLabelsAvailable()
    {
        return true;
    }
    
    public function getTracking($trackings)
    {
    }
    
    protected function setTrackingRequest()
    {
    }
}
