<?php
Mage::Log(__FILE__);

$installer = $this;
$installer->startSetup();
$installer->addAttributeGroup('catalog_product', 'Default', 'Smartsend');
$installer->addAttribute('catalog_product', 'smartsend_height',
                array(
                    'type' => 'decimal',
                    'input' => 'text',
                    'label' => 'Height',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->updateAttribute('catalog_product', $installer->getAttribute('catalog_product', 'smartsend_height', 'attribute_id'), array('apply_to' => 'simple'), null, null);
$installer->addAttribute('catalog_product', 'smartsend_length',
                array(
                    'type' => 'decimal',
                    'input' => 'text',
                    'label' => 'Length',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->updateAttribute('catalog_product', $installer->getAttribute('catalog_product', 'smartsend_length', 'attribute_id'), array('apply_to' => 'simple'), null, null);
$installer->addAttribute('catalog_product', 'smartsend_width',
                array(
                    'type' => 'decimal',
                    'input' => 'text',
                    'label' => 'Width',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->updateAttribute('catalog_product', $installer->getAttribute('catalog_product', 'smartsend_width', 'attribute_id'), array('apply_to' => 'simple'), null, null);
$installer->addAttribute('catalog_product', 'smartsend_weight',
                array(
                    'type' => 'decimal',
                    'input' => 'text',
                    'label' => 'Weight',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->updateAttribute('catalog_product', $installer->getAttribute('catalog_product', 'smartsend_weight', 'attribute_id'), array('apply_to' => 'simple'), null, null);
$installer->addAttribute('catalog_product', 'smartsend_taillift_booking',
                array(
                    'type' => 'text',
                    'input' => 'select',
                    'label' => 'Tail-lift Booking',
                    'source' => 'smartsend/shipping_carrier_smartsend_source_tailliftbooking',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->updateAttribute('catalog_product', $installer->getAttribute('catalog_product', 'smartsend_taillift_booking', 'attribute_id'), array('apply_to' => 'simple'), null, null);
$installer->addAttribute('catalog_product', 'smartsend_package_description',
                array(
                    'type' => 'text',
                    'input' => 'select',
                    'label' => 'Package Description',
                    'source' => 'smartsend/shipping_carrier_smartsend_source_packagedescription',
                    'visible' => false,
                    'required' => true,
                    'group' => 'Smartsend',
                    'position' => 10,
                ));
$installer->updateAttribute('catalog_product', $installer->getAttribute('catalog_product', 'smartsend_package_description', 'attribute_id'), array('apply_to' => 'simple'), null, null);
$installer->endSetup();
