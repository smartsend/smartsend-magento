<?php
Mage::Log(__FILE__);

$installer = $this;
$installer->startSetup();
$installer->run("
-- DROP TABLE IF EXISTS {$this->getTable('smartsend_products')};
CREATE TABLE IF NOT EXISTS {$this->getTable('smartsend_products')} (
  `id` int(11) NOT NULL auto_increment,
  `product_id` int(11) NOT NULL default '0',
  `packagedescription` varchar(20) NOT NULL default '',
  `weight` int(11) NOT NULL default '0',
  `length` int(11) NOT NULL default '0',
  `width` int(11) NOT NULL default '0',
  `height` int(11) NOT NULL default '0',
  `taillift` varchar(20) NOT NULL default 'None',
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
$installer->endSetup();