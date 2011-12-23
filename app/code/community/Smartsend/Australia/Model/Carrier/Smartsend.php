<?php
 
 class Smartsend_Australia_Model_Carrier_Smartsend
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'smartsend';
	public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {        
    	// Check if this method is active
		if (!$this->getConfigFlag('active')) 
		{
			return false;
		}
		$result = Mage::getModel('shipping/rate_result');		
		// Switch between domestic and international shipping methods based
		//{
			$arr_resp = $this->_drcRequest($request);
				$quote_count = ((int) $arr_resp["QUOTECOUNT"]) - 1;
				# ASSIGNING VALUES TO ARRAY METHODS    
				for ($x=0; $x<=$quote_count; $x++){	
					$title = $this->getConfigData('name') . " " . 
					$method = $this->_createMethod($request, $x, $title, $arr_resp["QUOTE({$x})_TOTAL"], $arr_resp["QUOTE({$x})_TOTAL"]);
				}
        //}
        return $result;
    }
    
    protected function _createMethod($request, $method_code, $title, $price, $cost)
    {
		$method = Mage::getModel('shipping/rate_result_method');
		$method->setCarrierTitle($this->getConfigData('title'));
		$method->setPrice($this->getFinalPriceWithHandlingFee($price));
		$method->setCost($cost);
		return $method;
    }
	protected function _drcRequest($service)
	{
		$origCountry = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
		if ($origCountry != "AU") 
		{
			return false;
		}		
		// TODO: Add some more validations
		//$frompcode = Mage::getStoreConfig('shipping/origin/postcode', $this->getStore());
		//$fromsuburb = Mage::getStoreConfig('shipping/origin/city', $this->getStore());
		$topcode = $service->getDestPostcode();
		$tosuburb = $service->getDestCity();
		if ($service->getDestCountryId()) {
			$destCountry = $service->getDestCountryId();
		} 
		else{
			$destCountry = "AU";
		}		
		// Here we get the weight (and convert it to grams) and set some
		// sensible defaults for other shipping parameters.	
		$weight = (int)$service->getPackageWeight();
		$shipping_num_boxes = 1;
		$Description = "CARTON";
		$post_url = "http://api.smartsend.com.au/";    
    
    $post_param_values["METHOD"]                = "GetQuote";
    $post_param_values["FROMCOUNTRYCODE"]       = $origCountry;
    $post_param_values["FROMPOSTCODE"]          = $frompcode; //"2000";
    $post_param_values["FROMSUBURB"]            = $fromsuburb; //"SYDNEY";
    $post_param_values["TOCOUNTRYCODE"]         = $destCountry;
    $post_param_values["TOPOSTCODE"]            = $topcode;
    $post_param_values["TOSUBURB"]              = $tosuburb;

    # tail lift - init    
    $taillift = array();
    $key = 0;
	$freeBoxes = 0;
        if ($service->getAllItems()) {
            foreach ($service->getAllItems() as $item) {
	/* fetching the values of lenght,weight,height,description in smartsend_products table */
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                            $freeBoxes += $item->getQty() * $child->getQty();
                        }
                    }
                } elseif ($item->getFreeShipping()) {
                    $freeBoxes += $item->getQty();
                }
            }
        }
        $this->setFreeBoxes($freeBoxes);
        $post_value_items["ITEM({$key})_HEIGHT"]         =  $height;
        $post_value_items["ITEM({$key})_LENGTH"]         =  $length;
        $post_value_items["ITEM({$key})_DEPTH"]          =  $width;
        $post_value_items["ITEM({$key})_WEIGHT"]         =  $width;
        $post_value_items["ITEM({$key})_DESCRIPTION"]    =  $Description;
    # tail lift - choose appropriate value
    $post_param_values["TAILLIFT"] = "none";            
    if (in_array("none",  $taillift))                                               $post_param_values["TAILLIFT"]      = "none";           
    if (in_array("atpickup",  $taillift))                                           $post_param_values["TAILLIFT"]      = "atpickup";
    if (in_array("atdestination",  $taillift))                                      $post_param_values["TAILLIFT"]      = "atdestination";
    if (in_array("atpickup",  $taillift) && in_array("atdestination",  $taillift))  $post_param_values["TAILLIFT"]      = "both";
    if (in_array("both",  $taillift))                                               $post_param_values["TAILLIFT"]      = "both";   
       
    $post_final_values = array_merge($post_param_values,$post_value_items);
    # POST PARAMETER AND ITEMS VALUE URLENCODE
    $post_string = "";
    foreach( $post_final_values as $key => $value )
            { $post_string .= "$key=" . urlencode( $value ) . "&"; }
    $post_string = rtrim( $post_string, "& " );
	if ($service->getFreeShipping() === true || $service->getPackageQty() == $this->getFreeBoxes()) {
    $arr_resp['ACK'] = 'Success';
    }else{
    
    # START CURL PROCESS
    $request = curl_init($post_url); 
    curl_setopt($request, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE);
    $post_response = curl_exec($request); 
    curl_close ($request); // close curl object    
	# parse output
    parse_str($post_response, $arr_resp);
	}
	return $arr_resp;
	}

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array('smartsend' => $this->getConfigData('name'));
    }
	
	
}
?>