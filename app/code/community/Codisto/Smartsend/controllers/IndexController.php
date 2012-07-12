<?php
Mage::Log(__FILE__);
class Codisto_Smartsend_IndexController
    extends Mage_Core_Controller_Front_Action
{
    function indexAction()
    {
		Mage::Log(__METHOD__);
        echo "Smartsend Index Controller";
    }
}