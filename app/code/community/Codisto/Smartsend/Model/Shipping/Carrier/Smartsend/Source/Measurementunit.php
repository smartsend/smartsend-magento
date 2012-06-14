<?php
class Codisto_Smartsend_Model_Shipping_Carrier_Smartsend_Source_Measurementunit
{
    public function toOptionArray()
    {
        $smartsend = Mage::getSingleton('smartsend/shipping_carrier_smartsend');
        $arr = array();
        foreach ($smartsend->getCode('measurementunit') as $k => $v)
            $arr[] = array('value' => $k, 'label' => $v);

        return $arr;
    }
}
