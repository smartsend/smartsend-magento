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
		// Switch between domestic and international shipping methods based		// on destination country.		//if($destCountry == "AU")
		//{
			$arr_resp = $this->_drcRequest($request);
				$quote_count = ((int) $arr_resp["QUOTECOUNT"]) - 1;
				# ASSIGNING VALUES TO ARRAY METHODS    
				for ($x=0; $x<=$quote_count; $x++){	
					$title = $this->getConfigData('name') . " " . 					ucfirst(strtolower($arr_resp["QUOTE($x)_ESTIMATEDTRANSITTIME"])) ;
					$method = $this->_createMethod($request, $x, $title, $arr_resp["QUOTE({$x})_TOTAL"], $arr_resp["QUOTE({$x})_TOTAL"]);					$result->append($method);
				}
        //}
        return $result;
    }
    
    protected function _createMethod($request, $method_code, $title, $price, $cost)
    {
		$method = Mage::getModel('shipping/rate_result_method');		$method->setCarrier('smartsend');
		$method->setCarrierTitle($this->getConfigData('title'));		$method->setMethod($method_code);		$method->setMethodTitle($title);
		$method->setPrice($this->getFinalPriceWithHandlingFee($price));
		$method->setCost($cost);
		return $method;
    }
	protected function _drcRequest($service)
	{		// Check if this method is even applicable (shipping from Australia)		$db = Mage::getSingleton('core/resource')->getConnection('core_write');		
		$origCountry = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
		if ($origCountry != "AU") 
		{
			return false;
		}		
		// TODO: Add some more validations		$path_smartsend = "carriers/smartsend/";		
		//$frompcode = Mage::getStoreConfig('shipping/origin/postcode', $this->getStore());
		//$fromsuburb = Mage::getStoreConfig('shipping/origin/city', $this->getStore());				$frompcode = Mage::getStoreConfig($path_smartsend.'post_code', $this->getStore());		$fromsuburb = Mage::getStoreConfig($path_smartsend.'suburban', $this->getStore());		
		$topcode = $service->getDestPostcode();
		$tosuburb = $service->getDestCity();		Mage::Log($frompcode);		Mage::Log($fromsuburb);		
		if ($service->getDestCountryId()) {
			$destCountry = $service->getDestCountryId();
		} 
		else{
			$destCountry = "AU";
		}			
		// Here we get the weight (and convert it to grams) and set some
		// sensible defaults for other shipping parameters.	
		$weight = (int)$service->getPackageWeight();				$height = $width = $length = 100;
		$shipping_num_boxes = 1;
		$Description = "CARTON";
		$post_url = "http://api.smartsend.com.au/";    	
    	//$result = $db->query("SELECT depth,length,height,description,taillift FROM 'smartsend_products'");		
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
	/* fetching the values of lenght,weight,height,description in smartsend_products table */				$prod_id = $item->getProduct()->getId();								
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
                }								$prod_id 	= $item->getProduct()->getId();				$result 	= $db->query('Select * from `smartsend_products` where id='."'".$prod_id."'");				$rows 		= $result->fetch(PDO::FETCH_ASSOC);												if($rows){					$post_value_items["ITEM({$key})_HEIGHT"]         =  $rows['height'];					$post_value_items["ITEM({$key})_LENGTH"]         =  $rows['length'];					$post_value_items["ITEM({$key})_DEPTH"]          =  $rows['depth'];					$post_value_items["ITEM({$key})_WEIGHT"]         =  $weight;					$post_value_items["ITEM({$key})_DESCRIPTION"]    =  $rows['description'];				}else{					/* default values */					$post_value_items["ITEM({$key})_HEIGHT"]         =  1;					$post_value_items["ITEM({$key})_LENGTH"]         =  1;					$post_value_items["ITEM({$key})_DEPTH"]          =  1;					$post_value_items["ITEM({$key})_WEIGHT"]         =  $weight;					$post_value_items["ITEM({$key})_DESCRIPTION"]    =  'none';								}                    # tail lift - assigns value                    switch($rows['taillift']){                        case 'none':                            $taillift[] = "none";break;                        case 'atpickup':                            $taillift[] = "atpickup";break;                            case 'atdestination':                            $taillift[] = "atdestination";break;                                                                                 case 'both':                            $taillift[] = "both";break;                                                                             }									$key++;
            }
        }				
        $this->setFreeBoxes($freeBoxes);							/*
        $post_value_items["ITEM({$key})_HEIGHT"]         =  $height;
        $post_value_items["ITEM({$key})_LENGTH"]         =  $length;
        $post_value_items["ITEM({$key})_DEPTH"]          =  $width;
        $post_value_items["ITEM({$key})_WEIGHT"]         =  $width;
        $post_value_items["ITEM({$key})_DESCRIPTION"]    =  $Description;*/           		
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
	if ($service->getFreeShipping() === true || $service->getPackageQty() == $this->getFreeBoxes()) {                $shippingPrice = '0.00';				
    $arr_resp['ACK'] = 'Success';    $arr_resp['QUOTE(0)_TOTAL'] = $shippingPrice;    $arr_resp['QUOTE(0)_ESTIMATEDTRANSITTIME'] = 'Fixed';    $arr_resp['QUOTECOUNT'] = 1;
    }else{
    
    # START CURL PROCESS
    $request = curl_init($post_url); 	curl_setopt($request, CURLOPT_HEADER, 0); 	curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); 
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