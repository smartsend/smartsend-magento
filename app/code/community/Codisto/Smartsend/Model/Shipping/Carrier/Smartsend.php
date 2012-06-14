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

    protected function _createMethod($method, $title, $cost)
    {
        $newMethod = Mage::getModel('shipping/rate_result_method');
        $newMethod->setCarrier($this->_code);
        $newMethod->setCarrierTitle($this->getConfigData('title'));
        $newMethod->setMethod($method);
        $newMethod->setMethodTitle($title);
        $newMethod->setPrice($this->getFinalPriceWithHandlingFee($cost));
        $newMethod->setCost($cost);

        return $newMethod;
    }

    public function getCarrierCode()
    {
        return $this->_code;
    }

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        // Check if this method is active
        if (!$this->getConfigFlag('active'))
        {
            Mage::Log('Shipping method ' . $this->_code . ' is not active, not collecting rates');
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
            $method = $this->_createMethod($quote["method"], $quote["title"], $quote["cost"]);
            $this->_result->append($method);
        }

        /* TODO: Add error reporting code, something similar to the following if any errors arise.
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage('Some error message to present');
            return $error;
        */
        return $this->_result;
    }
    
    public function requestToShipment(Mage_Shipping_Model_Shipment_Request $request)
    {
        Mage::Log("Smartsend requestToShipment called");
        
        return false;
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
                                'SERVICETYPE' => $this->getConfigData('allowed_servicetypes'),
                                'RECEIPTEDDELIVERY' => $this->getConfigData('receipted_delivery'),
                                'TRANSPORTASSURANCE' => '0', // TODO: Determine whether this should be customer or merchant choice - affects cost
                                'TAILLIFT' => $this->getConfigData('taillift_booking'),
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
                                'USERCODE' => $this->getConfigData('corporate_client_code'),
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
//        Mage::Log("API Request String is '" . $apiRequestString . "'");

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
                parse_str($apiResponse, $apiResult);
                if(strtoupper($apiResult["ACK"]) == "SUCCESS")
                {
                    $quotes = array();
                    for($i = 0; $i < $apiResult["QUOTECOUNT"]; $i++)
                    {
                        $service = $apiResult["QUOTE(" . $i . ")_SERVICE"];
                        $estTransitTime = $apiResult["QUOTE(" . $i . ")_ESTIMATEDTRANSITTIME"];
                        $total = $apiResult["QUOTE(" . $i . ")_TOTAL"];
                        
                        $quote = array(
                                        'method' => $service,
                                        'title' => ($estTransitTime != "" ? $service . " (" . $estTransitTime . ")" : $service),
                                        'cost' => $total,
                                    );
                        $quotes[] = $quote;
                    }
                }
                elseif(strtoupper($apiResult["ACK"]) == "FAILED")
                {
                    Mage::Log("Smartsend request failed, raw response received: " . var_export($apiResult, true));
                }
                else
                {
                    Mage::Log("Unknown response received from Smartsend API request, raw response received: " . var_export($apiResult, true));
                }
            }
            curl_close ($apiRequest);
            return $quotes;
        }
    }

    public function getCode($type, $code = '')
    {
        static $codes;
        $codes = array(
            'measurementunit' => array(
                'Centimetres' => Mage::helper('smartsend')->__('Centimetres'),
                'Metres' => Mage::helper('smartsend')->__('Metres'),
                'Feet' => Mage::helper('smartsend')->__('Feet'),
                'Yards' => Mage::helper('smartsend')->__('Yards'),
            ),
            'packagedescription' => array(
                'Envelope' => Mage::helper('smartsend')->__('Envelope'),
                'Carton' => Mage::helper('smartsend')->__('Carton'),
                'Satchel / Bag' => Mage::helper('smartsend')->__('Satchel / Bag'),
                'Tube' => Mage::helper('smartsend')->__('Tube'),
                'Skid' => Mage::helper('smartsend')->__('Skid'),
                'Pallet' => Mage::helper('smartsend')->__('Pallet'),
                'Crate' => Mage::helper('smartsend')->__('Crate'),
                'Flat Pack' => Mage::helper('smartsend')->__('Flat Pack'),
                'Roll' => Mage::helper('smartsend')->__('Roll'),
                'Length' => Mage::helper('smartsend')->__('Length'),
                'Tyre / Wheel' => Mage::helper('smartsend')->__('Tyre / Wheel'),
                'Furniture / Bedding' => Mage::helper('smartsend')->__('Furniture / Bedding'),
            ),
            'receipteddelivery' => array(
                'Always require receipt' => Mage::helper('smartsend')->__('Always require receipt'),
                'Request receipt' => Mage::helper('smartsend')->__('Request receipt'),
                'No receipt' => Mage::helper('smartsend')->__('No receipt'),
                'Never require receipt' => Mage::helper('smartsend')->__('Never require receipt'),
            ),
            'servicetype' => array(
                'All' => Mage::helper('smartsend')->__('All'),
                'Satchel' => Mage::helper('smartsend')->__('Satchel'),
                'Road' => Mage::helper('smartsend')->__('Road'),
                'Express' => Mage::helper('smartsend')->__('Express'),
            ),
            'tailliftbooking' => array(
                'None' => Mage::helper('smartsend')->__('None'),
                'At Pickup' => Mage::helper('smartsend')->__('At Pickup'),
                'At Destination' => Mage::helper('smartsend')->__('At Destination'),
                'Both' => Mage::helper('smartsend')->__('Both'),
            ),
            'weightunit' => array(
                'Kilograms' => Mage::helper('smartsend')->__('Kilograms'),
                'Grams' => Mage::helper('smartsend')->__('Grams'),
                'Pounds' => Mage::helper('smartsend')->__('Pounds'),
                'Ounces' => Mage::helper('smartsend')->__('Ounces'),
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
    
    public function isTrackingAvailable()
    {
        return true;
    }
    
    public function getTrackingInfo($tracking)
    {
        $info = array();
        $result = $this->getTracking($tracking);
        
        if($result instanceof Mage_Shipping_Model_Tracking_Result)
        {
            if($trackings = $result->getAllTrackings())
            {
                return $trackings[0];
            }
        }
        elseif(is_string($result) && !empty($result))
            return $result;

        return false;
    }
    
    public function getTracking($trackings)
    {
    }
    
    protected function setTrackingRequest()
    {
    }
}
