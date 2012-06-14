<?php
Mage::Log(__FILE__);

class Codisto_Smartsend_Model_Shipping_Carrier_Smartsend_Source_Tailliftbooking
    extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    public function getAllOptions()
    {
        $options = self::toOptionArray();
        array_unshift($options, array(
                                    'value' => '',
                                    'label' => Mage::helper('smartsend')->__('-- Please Select --'),
                                ));
        return $options;
    }

    public function getOptionText($optionId)
    {
        $options = self::toOptionArray();
        return isset($options[$optionId]) ? $options[$optionId] : null;
    }

    public function toOptionArray()
    {
        $smartsend = Mage::getSingleton('smartsend/shipping_carrier_smartsend');
        $arr = array();
        foreach ($smartsend->getCode('tailliftbooking') as $k => $v)
            $arr[] = array('value' => $k, 'label' => $v);

        return $arr;
    }
}
