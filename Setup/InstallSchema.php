<?php

namespace BigBridge\ProductImport\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @author Patrick van Bergen
 */
class InstallSchema implements InstallSchemaInterface
{
    const TABLE_CATALOG_PRODUCT_ENTITY_VARCHAR = 'catalog_product_entity_varchar';

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $table = $installer->getTable(self::TABLE_CATALOG_PRODUCT_ENTITY_VARCHAR);

        // adds an index to speed up the selection of values in the VARCHAR tables
        // this is necessary to check the uniqueness of url_key
        // See also https://sourceforge.net/p/magmi/patches/23/
        $installer->getConnection()->addIndex($table, 'CATALOG_PRODUCT_ENTITY_VARCHAR_ATTRIBUTE_ID_VALUE', ['attribute_id', 'value']);
    }
}
