<?php
Mage::Log(__FILE__);

$installer = $this;
$installer->startSetup();
$installer->addAttributeGroup('catalog_product', 'Default', 'Smartsend', 1000);
$installer->addAttribute('catalog_product', 'smartsendheight',
                array(
                    'type' => 'decimal',
                    'input' => 'text',
                    'label' => 'Height',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->addAttribute('catalog_product', 'smartsendlength',
                array(
                    'type' => 'decimal',
                    'input' => 'text',
                    'label' => 'Length',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->addAttribute('catalog_product', 'smartsendwidth',
                array(
                    'type' => 'decimal',
                    'input' => 'text',
                    'label' => 'Width',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->addAttribute('catalog_product', 'smartsendweight',
                array(
                    'type' => 'decimal',
                    'input' => 'text',
                    'label' => 'Weight',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->addAttribute('catalog_product', 'smartsendtailliftbooking',
                array(
                    'type' => 'text',
                    'input' => 'select',
                    'label' => 'Tail-lift Booking',
                    'source' => 'smartsend/shipping_carrier_smartsend_source_tailliftbooking',
                    'backend' => 'eav/entity_attribute_backend_array',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->addAttribute('catalog_product', 'smartsendpackagedescription',
                array(
                    'type' => 'text',
                    'input' => 'select',
                    'label' => 'Package Description',
                    'source' => 'smartsend/shipping_carrier_smartsend_source_packagedescription',
                    'backend' => 'eav/entity_attribute_backend_array',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->endSetup();
