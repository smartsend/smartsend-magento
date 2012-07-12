<?php
Mage::Log(__FILE__);
class Codisto_Smartsend_Model_Shipping_Carrier_Smartsend
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    const CODE = 'smartsend';
    const AUSTRALIA_COUNTRY_CODE = 'AU';

	public $GatewayURL = 'https://api.dev.smartsend.com.au/';

    protected $_code = self::CODE;
    protected $_request = null;
    protected $_result = null;
    protected $_errors = array();


    protected function _createMethod($method, $title, $cost)
    {
		Mage::Log(__METHOD__);
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
		Mage::Log(__METHOD__);
        return $this->_code;
    }

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        Mage::Log(__METHOD__);
		
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
            
        if ($quotes instanceof Mage_Shipping_Model_Rate_Result_Error)
        {
            Mage::Log('An error has been returned from _getQuotes call');
            
            // An error has been returned, pass it up.
            
            $this->_result = $quotes;
            return $this->_result;
        }

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
		Mage::Log(__METHOD__);
        return false;
    }

    protected function _getQuotes()
    {
		Mage::Log(__METHOD__);
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
                                                    'HEIGHT' => 'required in cm',
                                                    'DESCRIPTION' => 'CARTON',
                                                ),
                                            ),
                                */
                                'BOXES' => array(),
                                /*
                                'BOXES' => array(
                                                array(
                                                    'WEIGHT' => 'max weight required in kgs',
                                                    'WIDTH' => 'required in cm',
                                                    'LENGTH' => 'required in cm',
                                                    'HEIGHT' => 'required in cm',
                                            ),
                                */
                                'USERCODE' => $this->getConfigData('corporate_client_code'),
                            );

        // Fill in item entries, getAllItems() will returns sub products of bundled products.
        foreach ($this->_request->getAllItems() as $item)
        {
            // We can only calculate freight on non-virtual Simple and Grouped items, as they will have Smartsend freight dimensions and weight.
            /*
                Values for debugging purposes
            Mage::Log("Item Name: " . $item->getName());
            Mage::Log("Item Qty: " . $item->getQty());
            Mage::Log("IsVirtual: " . $item->getProduct()->isVirtual());
            Mage::Log("HasParent: " . ($item->getParentItem() != null));
            Mage::Log("Parent Qty: " . ($item->getParentItem() != null ? $item->getParentItem()->getQty() : "n/a"));
            */

            if($item->getProduct()->isVirtual() ||
                ($item->getProductType() != 'simple' && $item->getProductType() != 'grouped'))
                continue;

            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            $measurementUnit = $this->getConfigData('default_measurement_unit');
            $weightUnit = $this->getConfigData('default_weight_unit');

            for($i = 0; $i < ($item->getParentItem() == null ? 1 : $item->getParentItem()->getQty()); $i++)
            {
                // TODO: Check for free shipping on products and don't count them in the items to query for SmartSend quote
                $weight = $product->getSmartsendWeight();
                $width = $product->getSmartsendWidth();
                $length = $product->getSmartsendLength();
                $height = $product->getSmartsendHeight();
                $description = $product->getSmartsendPackageDescription();
                $tailliftbooking = $product->getSmartsendProductBooking();

                $itemProperties = array(
                                        'WEIGHT' => $weight == null ? $this->getConfigData('default_weight') : $weight,
                                        'WIDTH' => $width == null ? $this->getConfigData('default_width') : $width,
                                        'LENGTH' => $length == null ? $this->getConfigData('default_length') : $length,
                                        'HEIGHT' => $height == null ? $this->getConfigData('default_height') : $height,
                                        'DESCRIPTION' => strtoupper($description == null ? $this->getConfigData('default_package_description') : $description),
                                        'QTY' => $item->getQty(),
                                        'TAILLIFT' => $tailliftbooking == null ? $this->getConfigData('taillift_booking') : $tailliftbooking,
                                    );
                // Convert measurement and weight units to Smartsend's centimetres and kilograms

                switch($measurementUnit)
                {
                    case 'Centimetres': break;
                    case 'Metres': // 1 m = 100 cm
                        $itemProperties['WIDTH'] *= 100.0;
                        $itemProperties['LENGTH'] *= 100.0;
                        $itemProperties['HEIGHT'] *= 100.0;
                        break;
                    case 'Inches': // 1 in = 2.54 cm
                        $itemProperties['WIDTH'] *= 2.54;
                        $itemProperties['LENGTH'] *= 2.54;
                        $itemProperties['HEIGHT'] *= 2.54;
                        break;
                    case 'Feet': // 1 ft = 30.48 cm
                        $itemProperties['WIDTH'] *= 30.48;
                        $itemProperties['LENGTH'] *= 30.48;
                        $itemProperties['HEIGHT'] *= 30.48;
                        break;
                    case 'Yards': // 1 yd = 91.44 cm
                        $itemProperties['WIDTH'] *= 91.44;
                        $itemProperties['LENGTH'] *= 91.44;
                        $itemProperties['HEIGHT'] *= 91.44;
                        break;
                    default: break;
                }

                switch($weightUnit)
                {
                    case 'Grams': // 1 gm = 0.001 kg
                        $itemProperties['WEIGHT'] *= 0.001;
                        break;
                    case 'Kilograms': break;
                    case 'Pounds': // 1 lb = 0.45359237 gm
                        $itemProperties['WEIGHT'] *= 0.45359237;
                        break;
                    case 'Ounces': // 1 oz = 0.0283495231
                        $itemProperties['WEIGHT'] *= 0.0283495231;
                        break;
                    default: break;
                }

                // Check whether we should update taillift booking status
                if($apiRequestParams['TAILLIFT'] != $itemProperties['TAILLIFT'] &&
                    $apiRequestParams['TAILLIFT'] != 'Both')
                {
                    if($apiRequestParams['TAILLIFT'] == 'None' || $apiRequestParams['TAILLIFT'] == null)
                        $apiRequestParams['TAILLIFT'] = $itemProperties['TAILLIFT'];
                    else
                        $apiRequestParams['TAILLIFT'] = 'Both';
                }
                $apiRequestParams['ITEMS'][] = $itemProperties;
            }
        }

        $apiRequestString = "";
        foreach ($apiRequestParams as $paramKey => $paramValue)
        {
            if($paramKey == "ITEMS" || $paramKey == "BOXES")
            {
                for($i = 0; $i < count($paramValue); $i++)
                {
                    $apiRequestString .= rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_" . ($paramKey == "BOXES" ? "MAX" : "") . "WEIGHT") . "=" . rawurlencode($paramValue[$i]['WEIGHT']) . "&"
                                        . rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_WIDTH") . "=" . rawurlencode($paramValue[$i]['WIDTH']) . "&"
                                        . rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_LENGTH") . "=" . rawurlencode($paramValue[$i]['LENGTH']) . "&"
                                        . rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_HEIGHT") . "=" . rawurlencode($paramValue[$i]['HEIGHT']) . "&"
                                        . rawurlencode(($paramKey == "ITEMS" ? "ITEM(" : "BOX(") . $i . ")_DESCRIPTION") . "=" . rawurlencode($paramValue[$i]['DESCRIPTION']) . "&"
                                        . ($paramKey == "ITEMS" ? rawurlencode("ITEM(" . $i . ")_QTY") . "=" . rawurlencode($paramValue[$i]['QTY']) . "&" : "");
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
                                                CURLOPT_URL => $this->GatewayURL,
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
                Mage::Log("API Response: " . var_export($apiResult, true));
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
		Mage::Log(__METHOD__);
        static $codes;
        $codes = array(
            'measurement_unit' => array(
                'Centimetres' => Mage::helper('smartsend')->__('Centimetres'),
                'Metres' => Mage::helper('smartsend')->__('Metres'),
                'Inches' => Mage::helper('smartsend')->__('Inches'),
                'Feet' => Mage::helper('smartsend')->__('Feet'),
                'Yards' => Mage::helper('smartsend')->__('Yards'),
            ),
            'package_description' => array(
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
            'receipted_delivery' => array(
                'Always require receipt' => Mage::helper('smartsend')->__('Always require receipt'),
                'Request receipt' => Mage::helper('smartsend')->__('Request receipt'),
                'No receipt' => Mage::helper('smartsend')->__('No receipt'),
                'Never require receipt' => Mage::helper('smartsend')->__('Never require receipt'),
            ),
            'service_type' => array(
                'All' => Mage::helper('smartsend')->__('All'),
                'Satchel' => Mage::helper('smartsend')->__('Satchel'),
                'Road' => Mage::helper('smartsend')->__('Road'),
                'Express' => Mage::helper('smartsend')->__('Express'),
            ),
            'taillift_booking' => array(
                'None' => Mage::helper('smartsend')->__('None'),
                'AtPickup' => Mage::helper('smartsend')->__('At Pickup'),
                'AtDestination' => Mage::helper('smartsend')->__('At Destination'),
                'Both' => Mage::helper('smartsend')->__('Both'),
            ),
            'weight_unit' => array(
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
		Mage::Log(__METHOD__);
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = array();
        foreach ($allowed as $k)
            $arr[$k] = $this->getCode('service', $k);
        
        return $arr;
    }

    public function isCityRequired()
    {
		Mage::Log(__METHOD__);
        return true;
    }
    
    public function isZipCodeRequired()
    {
		Mage::Log(__METHOD__);
        return true;
    }
    
    public function isStateProvinceRequired()
    {
		Mage::Log(__METHOD__);
        return true;
    }
    
    public function isShippingLabelsAvailable()
    {
		Mage::Log(__METHOD__);
        return false;
    }
    
    public function isTrackingAvailable()
    {
		Mage::Log(__METHOD__);
        return true;
    }
	
	/* Get tracking response - returns string */
	public function getResponse()
	{
		Mage::Log(__METHOD__);
		return '';
	}
    
    public function getTrackingInfo($tracking)
    {
		Mage::Log(__METHOD__);
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
		Mage::Log(__METHOD__);
		return null;
    }
    
    protected function setTrackingRequest()
    {
		Mage::Log(__METHOD__);
    }
}
