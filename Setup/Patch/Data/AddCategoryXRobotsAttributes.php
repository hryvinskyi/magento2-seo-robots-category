<?php
/**
 * Copyright (c) 2026. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Setup\Patch\Data;

use Hryvinskyi\SeoRobotsCategoryApi\Api\ConfigInterface;
use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCategoryXRobotsAttributes implements DataPatchInterface
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
     * AddCategoryXRobotsAttributes constructor.
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

        // 1. X-Robots-Tag Header Directives for Category
        $eavSetup->addAttribute(
            Category::ENTITY,
            ConfigInterface::X_ROBOTS_HEADER_ATTRIBUTE_CODE,
            [
                'type' => 'text',
                'label' => 'X-Robots-Tag Header Directives',
                'input' => 'multiselect',
                'backend' => \Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Backend\RobotsDirective::class,
                'required' => false,
                'sort_order' => 110,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'note' => 'X-Robots-Tag HTTP header directives for this category page'
            ]
        );

        // 2. Use Same as Meta Robots Tag toggle
        $eavSetup->addAttribute(
            Category::ENTITY,
            ConfigInterface::USE_META_FOR_X_ROBOTS_ATTRIBUTE_CODE,
            [
                'type' => 'int',
                'label' => 'Use Same as Meta Robots Tag',
                'input' => 'boolean',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'required' => false,
                'default' => '1',
                'sort_order' => 111,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'note' => 'When enabled, X-Robots-Tag header will use the same directives as meta robots tag'
            ]
        );

        // 3. Apply X-Robots-Tag to Category Products
        $eavSetup->addAttribute(
            Category::ENTITY,
            ConfigInterface::APPLY_X_ROBOTS_TO_PRODUCTS_ATTRIBUTE_CODE,
            [
                'type' => 'int',
                'label' => 'Apply X-Robots-Tag to Category Products',
                'input' => 'boolean',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'required' => false,
                'default' => '0',
                'sort_order' => 112,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'note' => 'Enable to apply X-Robots-Tag header to all products in this category'
            ]
        );

        // 4. X-Robots-Tag for Category Products
        $eavSetup->addAttribute(
            Category::ENTITY,
            ConfigInterface::PRODUCT_X_ROBOTS_HEADER_ATTRIBUTE_CODE,
            [
                'type' => 'text',
                'label' => 'X-Robots-Tag for Category Products',
                'input' => 'multiselect',
                'backend' => \Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Backend\RobotsDirective::class,
                'required' => false,
                'sort_order' => 113,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'note' => 'X-Robots-Tag HTTP header directives for products in this category'
            ]
        );

        // 5. Use Category X-Robots for Products toggle
        $eavSetup->addAttribute(
            Category::ENTITY,
            ConfigInterface::USE_CATEGORY_X_ROBOTS_FOR_PRODUCTS_ATTRIBUTE_CODE,
            [
                'type' => 'int',
                'label' => 'Use Category X-Robots for Products',
                'input' => 'boolean',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'required' => false,
                'default' => '1',
                'sort_order' => 114,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Search Engine Optimization',
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'note' => 'When enabled, products will use the category\'s X-Robots-Tag directives'
            ]
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [AddCategoryRobotsAttribute::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
