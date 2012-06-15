<?php
class Codisto_Smartsend_Model_Shipping_Carrier_Smartsend_Source_Weightunit
{
    public function toOptionArray()
    {
        $smartsend = Mage::getSingleton('smartsend/shipping_carrier_smartsend');
        $arr = array();
        foreach ($smartsend->getCode('weight_unit') as $k => $v)
            $arr[] = array('value' => $k, 'label' => $v);

        return $arr;
    }
}
