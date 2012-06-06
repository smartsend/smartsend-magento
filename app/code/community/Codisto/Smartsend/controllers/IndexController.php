<?php
class Codisto_Smartsend_IndexController
    extends Mage_Core_Controller_Front_Action
{
    function indexAction()
    {
        echo "Smartsend indexAction";
        $smartsend = Mage::getModel("Codisto_Smartsend/smartsend");
        $smartsend->smartsend("smartsend");
    }
}