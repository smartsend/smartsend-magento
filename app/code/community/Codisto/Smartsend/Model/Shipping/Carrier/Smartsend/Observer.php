<?php
Mage::Log(__FILE__);
class Codisto_Smartsend_Model_Shipping_Carrier_Smartsend_Observer
	extends Mage_Core_Model_Observer
{
	public function doSmartsendSalesOrderShipmentSaveBefore($observer)
	{
		Mage::Log(__METHOD__);
		/*
		Steps:
		1. check if we have smartsend as the shipping selection
		2. if so, do we have any shipment records for smartsend? if we do, don't call out to smartsend as we've done it already.
		3. if not, we need to call out to smartsend with setbooking to let the merchant set up shipping details.
		4. get called back from smartsend, store the smartsend booking token against the shipment we just went in on.
		5. In the notification controller work out whether we were successful, canceled or notified and handle appropriately
		*/
		
		// Check the shipment collection
		$shipmentEvent = $observer->getEvent()->getShipment();
		if($shipmentEvent)
		{
			$order_id = $shipmentEvent->getOrderId();
			Mage::Log("Order Id: " . $order_id);
			$order = Mage::getModel('sales/order');
			$order->load($order_id);
			
			if(!$order->canShip())
			{
				Mage::Log('Can not ship this order, most likely order has already been shipped');
				return $this;
			}
			Mage::Log('Can ship!');
			if($order->hasShipments())
			{
				Mage::Log('Order already has shipments - bailing!');
				return $this;
			}
			$shipments = $order->getShipmentsCollection();
			Mage::Log("Number of shipments: " . count($shipments));
			
			// Check we have no shipments and also determine if the shipping type on the order is smartsend
			if(count($shipments) == 0)
			{
				// Need to redirect the merchant out to Smartsend if we haven't processed the shipping as yet
				//		Mage::app()->getResponse()->setRedirect('http://www.google.com/')->sendResponse();
				// Just bailing for the moment!
				
				// Perform SetBooking call if it's a smartsend booking
				$apiRequestParams = array(
										'METHOD' => 'SETBOOKING',
										'RETURNURL' => Mage::getUrl('smartsend/notification/index', array('type' => 'return', 'order' => $order_id, '_secure' => true)),
										'NOTIFYURL' => Mage::getUrl('smartsend/notification/index', array('type' => 'notify', 'order' => $order_id, '_secure' => true)),
										'CANCELURL' => Mage::getUrl('smartsend/notification/index', array('type' => 'cancel', 'order' => $order_id, '_secure' => true)),
										'BOOKING(0)_CONTACTNAME' => 'Phillip Street (contact)',
										'BOOKING(0)_CONTACTPHONE' => '0405658958',
										'BOOKING(0)_CONTACTEMAIL' => 'pstreet@ontech.com.au',
										'BOOKING(0)_PICKUPCONTACT' => 'Phillip Street (pickup contact)',
										'BOOKING(0)_PICKUPADDRESS1' => '10 Tallowood St',
										'BOOKING(0)_PICKUPPHONE' => '0405658957',
										'BOOKING(0)_PICKUPSUBURB' => 'ALBION PARK',
										'BOOKING(0)_PICKUPSTATE' => 'NEW SOUTH WALES',
										'BOOKING(0)_PICKUPDATE' => '', 
										'BOOKING(0)_PICKUPTIMEID' => '',
										'BOOKING(0)_DESTCONTACT' => 'Phillip Street (dest contact)',
										'BOOKING(0)_DESTADDRESS1' => '10 Tallowood St',
										'BOOKING(0)_DESTPHONE' => '0405658957',
										'BOOKING(0)_DESTSUBURB' => 'ALBION PARK',
										'BOOKING(0)_DESTPOSTCODE' => '2527',
										'BOOKING(0)_DESTSTATE' => 'NEW SOUTH WALES',
										'BOOKING(0)_ITEMS' => array(),
										'TEST' => 'true',
									);
				Mage::Log("SetBooking Params:");
				Mage::Log($apiRequestParams);
				
				$apiRequestString = "";
				foreach($apiRequestParams as $paramKey => $paramValue)
				{
					if($paramKey == 'BOOKING(0)_ITEMS')
					{
						// TODO: bust up shipping items similar to getQuote effort
					}
					else
						$apiRequestString .= rawurlencode($paramKey) . "=" . rawurlencode($paramValue) . "&";
				}
				$apiRequest = curl_init();
				if($apiRequest == false)
				{
					Mage::Log("Failed to initialise cURL to perform smartsend API request");
					Mage::getSingleton('core/session')->addError(Mage::helper('smartsend')->__('Could not initial cURL to perform Smartsend booking'));
					return false;
				}
				else
				{
					$opts = array(
									CURLOPT_URL => Mage::getSingleton('smartsend/shipping_carrier_smartsend')->GatewayURL,
									CURLOPT_HEADER => 0,
									CURLOPT_RETURNTRANSFER => 1,
									CURLOPT_POSTFIELDS => $apiRequestString,
									CURLOPT_SSL_VERIFYPEER => false,
								);
					Mage::Log("Curl Options:");
					Mage::Log($opts);
					curl_setopt_array($apiRequest, $opts);
					$apiResponse = curl_exec($apiRequest);
					curl_close($apiRequest);
					Mage::Log($apiResponse);
					if($apiResponse != false)
					{
						// TODO: Redirect the merchant out to the BOOKINGURL returned if it's passed back
					}
					Mage::Log("Finished Curl Request");
				}
				exit;
			}
		}
		return $this;
	}
	
	public function doSmartsendSalesOrderShipmentSaveAfter($observer)
	{
		Mage::Log(__METHOD__);
		return $this;
	}
}