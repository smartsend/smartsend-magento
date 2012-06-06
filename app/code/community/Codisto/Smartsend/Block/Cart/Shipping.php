<?php
class Codisto_Smartsend_Block_Cart_Shipping
    extends Mage_Checkout_Block_Cart_Shipping
{
    public function getCityActive()
    {
        $smartsend = Mage::getSingleton('smartsend/shipping_carrier_smartsend');
        if($smartsend->getConfigFlag('active'))
            return true;
        else
            return parent::getCityActive();
    }
}