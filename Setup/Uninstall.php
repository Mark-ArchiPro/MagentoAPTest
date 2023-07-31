<?php

namespace ArchiPro\APIntegration\Setup;

class Uninstall implements \Magento\Framework\Setup\UninstallInterface
{
    /**
     * Module uninstall code
     *
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     * @return void
     */
    public function uninstall(
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\Setup\ModuleContextInterface $context
    ) {
        $setup->startSetup();

        var_dump('test');
        throw new \Exception('test');

        $setup->endSetup();
    }
}
