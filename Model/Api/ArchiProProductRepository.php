<?php

namespace ArchiPro\APIntegration\Model\Api;

use ArchiPro\APIntegration\Api\ArchiProProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Class ArchiProProductRepository
 */
class ArchiProProductRepository extends \Magento\Catalog\Model\ProductRepository implements ArchiProProductRepositoryInterface
{
    /**
     * @var \Magento\CatalogInventory\Model\Stock\StockItemRepository
     */
    private \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository;

    /**
     * @var \Magento\Catalog\Model\Category
     */
    private \Magento\Catalog\Model\Category $categoryModel;

    /**
     * @var \Magento\Swatches\Helper\Data
     */
    private \Magento\Swatches\Helper\Data $swatchHelper;

    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    private \Magento\Framework\App\ObjectManager $manager;

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return string[]
     */
    protected function fetchCategoryData(\Magento\Catalog\Api\Data\ProductInterface $product): array
    {
        $categoryIDs = $product->getCategoryIds();
        $categories = [];
        foreach ($categoryIDs as $categoryID) {
            $category = $this->categoryModel->load($categoryID);
            $categories[] = $category->getName();
        }
        return $categories;
    }


    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        // I don't think it's recommended to use the object manager directly, but the dependancy injection wasn't working for me
        $this->manager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->stockItemRepository = $this->manager->get(\Magento\CatalogInventory\Model\Stock\StockItemRepository::class);
        $this->categoryModel = $this->manager->create(\Magento\Catalog\Model\Category::class);
        $this->swatchHelper = $this->manager->get(\Magento\Swatches\Helper\Data::class);
        $result = parent::getList($searchCriteria);
        foreach ($result->getItems() as $product) {
            $qty = $this->stockItemRepository->get($product->getId())->getQty();
            $extensionattributes = $product->getExtensionAttributes();
            $categories = $this->fetchCategoryData($product);
            $extensionattributes->setStockRemaining($qty);
            $extensionattributes->setCategories($categories);
            $extensionattributes->setUrl($product->getProductUrl());
            $product->setExtensionAttributes($extensionattributes);
            $simpleProducts = [];
            $variantOptions = [];
            if ($product->getTypeId() === Configurable::TYPE_CODE) {
                $variations = $product->getTypeInstance()->getUsedProducts($product);
                $productAttributeOptions = $product->getTypeInstance()->getConfigurableOptions($product);
                foreach ($productAttributeOptions as $productAttribute) {
                    foreach ($productAttribute as $attribute) {

                        if(!isset($variantOptions[$attribute['sku']])) {
                            $variantOptions[$attribute['sku']] = ['sku' => $attribute['sku'], 'options' => []];
                        }

                        if(!isset($optionSwatchData[$attribute['value_index']])) {
                            $swatchData = $this->swatchHelper->getSwatchesByOptionsId([$attribute['value_index']]);
                            $optionSwatchData[$attribute['value_index']] = $swatchData;
                        } else {
                            $swatchData = $optionSwatchData[$attribute['value_index']];
                        }
                        $variantOptions[$attribute['sku']]['options'][] = ['label' => $attribute['super_attribute_label'], 'value' => $attribute['option_title'], 'swatch' => $swatchData[$attribute['value_index']] ?? []];
                    }
                }
                foreach ($variations as $variation) {
                    /** @var \Magento\Catalog\Api\Data\ProductInterface $variation */
                    $qty = $this->stockItemRepository->get($variation->getId())->getQty();
                    $configurableOptions = $variation->getAttributes();

                    $attributeOptions = [];
                    foreach ($configurableOptions as $test => $attribute) {
                        /** @var \Magento\Catalog\Api\Data\ProductAttributeInterface $attribute */
                        $attributeCode = $attribute->getAttributeCode();
                        $attributeValue = $variation->getData($attributeCode);
                        if ($attributeValue === null) {
                            continue;
                        }
                        $options = $attribute->getOptions();

                        $attributeValueLabel = null;
                        foreach ($options as $option) {
                            if ($option->getValue() == $attributeValue) {
                                $attributeValueLabel = $option->getLabel();
                            }
                        }
                        $attributeOptions[$attributeCode] = $attributeValueLabel ?? $attributeValue;
                    }

                    $simpleProducts[] = array_merge(
                        [
                        'options' => array_key_exists($variation->getSku(), $variantOptions) ? $variantOptions[$variation->getSku()]['options'] ?? [] : [],
                        'stock_remaining' => $qty,
                        ],
                        $attributeOptions
                    );
                }
            }
            $extensionattributes->setVariants($simpleProducts);
        }
        $result->setItems($result->getItems());
        return $result;
    }
}
