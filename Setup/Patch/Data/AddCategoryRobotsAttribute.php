<?php
/**
 * Copyright (c) 2025. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Setup\Patch\Data;

use Hryvinskyi\SeoRobotsCategoryApi\Api\ConfigInterface;
use Hryvinskyi\SeoRobotsApi\Api\RobotsListInterface;
use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCategoryRobotsAttribute implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * AddCategoryRobotsAttribute constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // 1. Robots Meta Tag for Category Page
        $eavSetup->addAttribute(
            Category::ENTITY,
            ConfigInterface::CATEGORY_ATTRIBUTE_CODE,
            [
                'type' => 'int',
                'label' => 'Robots Meta Tag',
                'input' => 'select',
                'source' => \Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Source\RobotsMetaTag::class,
                'required' => false,
                'sort_order' => 100,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => true,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => true,
                'note' => 'Controls search engine indexing for this category page'
            ]
        );

        // 2. Apply Robots to Category Products
        $eavSetup->addAttribute(
            Category::ENTITY,
            ConfigInterface::APPLY_ROBOTS_TO_PRODUCTS_ATTRIBUTE_CODE,
            [
                'type' => 'int',
                'label' => 'Apply Robots to Category Products',
                'input' => 'boolean',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'required' => false,
                'default' => '0',
                'sort_order' => 101,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'note' => 'Enable to apply robots meta tag to all products in this category'
            ]
        );

        // 3. Robots Meta Tag for Category Products
        $eavSetup->addAttribute(
            Category::ENTITY,
            ConfigInterface::PRODUCT_ROBOTS_ATTRIBUTE_CODE,
            [
                'type' => 'int',
                'label' => 'Robots for Category Products',
                'input' => 'select',
                'source' => \Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Source\ProductRobotsMetaTag::class,
                'required' => false,
                'sort_order' => 102,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'note' => 'Robots meta tag for products in this category (only applies if "Apply Robots to Category Products" is enabled)'
            ]
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
