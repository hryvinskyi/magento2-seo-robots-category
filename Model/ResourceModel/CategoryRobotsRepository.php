<?php
/**
 * Copyright (c) 2025. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Model\ResourceModel;

use Hryvinskyi\SeoRobotsCategoryApi\Api\CategoryRobotsRepositoryInterface;
use Hryvinskyi\SeoRobotsCategoryApi\Api\ConfigInterface;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

/**
 * Repository for fetching category robots data using direct SQL queries
 */
class CategoryRobotsRepository implements CategoryRobotsRepositoryInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @param ResourceConnection $resourceConnection
     * @param EavConfig $eavConfig
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        EavConfig $eavConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->eavConfig = $eavConfig;
    }

    /**
     * @inheritDoc
     */
    public function getProductRobotsDataByCategoryIds(array $categoryIds, int $storeId): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        // Get attribute IDs
        $applyRobotsAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::APPLY_ROBOTS_TO_PRODUCTS_ATTRIBUTE_CODE
        );
        $productRobotsAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::PRODUCT_ROBOTS_ATTRIBUTE_CODE
        );
        $categoryRobotsAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::CATEGORY_ATTRIBUTE_CODE
        );

        $useCategoryRobotsForProductsAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::USE_CATEGORY_ROBOTS_FOR_PRODUCTS_ATTRIBUTE_CODE
        );

        $applyRobotsAttrId = $applyRobotsAttr->getAttributeId();
        $productRobotsAttrId = $productRobotsAttr->getAttributeId();
        $categoryRobotsAttrId = $categoryRobotsAttr->getAttributeId();
        $useCategoryRobotsForProductsAttrId = $useCategoryRobotsForProductsAttr->getAttributeId();

        // Build query to get category robots data with store scope support
        // Only fetch categories where apply_robots_to_products = 1
        // Use COALESCE to prefer store-specific values, fall back to default (store_id = 0)
        $select = $connection->select()
            ->from(['cce' => $connection->getTableName('catalog_category_entity')], ['entity_id'])
            // Apply robots attribute (store-specific)
            ->joinLeft(
                ['apply_robots_store' => $connection->getTableName('catalog_category_entity_int')],
                'apply_robots_store.entity_id = cce.entity_id AND apply_robots_store.attribute_id = ' . $applyRobotsAttrId . ' AND apply_robots_store.store_id = ' . $storeId,
                []
            )
            // Apply robots attribute (default)
            ->joinLeft(
                ['apply_robots_default' => $connection->getTableName('catalog_category_entity_int')],
                'apply_robots_default.entity_id = cce.entity_id AND apply_robots_default.attribute_id = ' . $applyRobotsAttrId . ' AND apply_robots_default.store_id = 0',
                []
            )
            // Product robots attribute (store-specific) - text type after migration
            ->joinLeft(
                ['product_robots_store' => $connection->getTableName('catalog_category_entity_text')],
                'product_robots_store.entity_id = cce.entity_id AND product_robots_store.attribute_id = ' . $productRobotsAttrId . ' AND product_robots_store.store_id = ' . $storeId,
                []
            )
            // Product robots attribute (default) - text type after migration
            ->joinLeft(
                ['product_robots_default' => $connection->getTableName('catalog_category_entity_text')],
                'product_robots_default.entity_id = cce.entity_id AND product_robots_default.attribute_id = ' . $productRobotsAttrId . ' AND product_robots_default.store_id = 0',
                []
            )
            // Category robots attribute (store-specific) - text type after migration
            ->joinLeft(
                ['category_robots_store' => $connection->getTableName('catalog_category_entity_text')],
                'category_robots_store.entity_id = cce.entity_id AND category_robots_store.attribute_id = ' . $categoryRobotsAttrId . ' AND category_robots_store.store_id = ' . $storeId,
                []
            )
            // Category robots attribute (default) - text type after migration
            ->joinLeft(
                ['category_robots_default' => $connection->getTableName('catalog_category_entity_text')],
                'category_robots_default.entity_id = cce.entity_id AND category_robots_default.attribute_id = ' . $categoryRobotsAttrId . ' AND category_robots_default.store_id = 0',
                []
            )
            // Use category robots for products attribute (store-specific)
            ->joinLeft(
                ['use_category_robots_store' => $connection->getTableName('catalog_category_entity_int')],
                'use_category_robots_store.entity_id = cce.entity_id AND use_category_robots_store.attribute_id = ' . $useCategoryRobotsForProductsAttrId . ' AND use_category_robots_store.store_id = ' . $storeId,
                []
            )
            // Use category robots for products attribute (default)
            ->joinLeft(
                ['use_category_robots_default' => $connection->getTableName('catalog_category_entity_int')],
                'use_category_robots_default.entity_id = cce.entity_id AND use_category_robots_default.attribute_id = ' . $useCategoryRobotsForProductsAttrId . ' AND use_category_robots_default.store_id = 0',
                []
            )
            ->columns([
                'product_robots_meta_tag' => new Expression(
                    'COALESCE(product_robots_store.value, product_robots_default.value)'
                ),
                'robots_meta_tag' => new Expression(
                    'COALESCE(category_robots_store.value, category_robots_default.value)'
                ),
                'use_category_robots_for_products' => new Expression(
                    'COALESCE(use_category_robots_store.value, use_category_robots_default.value)'
                )
            ])
            ->where('cce.entity_id IN (?)', $categoryIds)
            ->where('COALESCE(apply_robots_store.value, apply_robots_default.value) = ?', 1);

        return $connection->fetchAll($select);
    }

    /**
     * @inheritDoc
     */
    public function getProductXRobotsDataByCategoryIds(array $categoryIds, int $storeId): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        // Get attribute IDs
        $applyXRobotsAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::APPLY_X_ROBOTS_TO_PRODUCTS_ATTRIBUTE_CODE
        );
        $xRobotsHeaderAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::X_ROBOTS_HEADER_ATTRIBUTE_CODE
        );
        $useMetaForXRobotsAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::USE_META_FOR_X_ROBOTS_ATTRIBUTE_CODE
        );
        $productXRobotsHeaderAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::PRODUCT_X_ROBOTS_HEADER_ATTRIBUTE_CODE
        );
        $useCategoryXRobotsForProductsAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::USE_CATEGORY_X_ROBOTS_FOR_PRODUCTS_ATTRIBUTE_CODE
        );
        $categoryRobotsAttr = $this->eavConfig->getAttribute(
            CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ConfigInterface::CATEGORY_ATTRIBUTE_CODE
        );

        $applyXRobotsAttrId = $applyXRobotsAttr->getAttributeId();
        $xRobotsHeaderAttrId = $xRobotsHeaderAttr->getAttributeId();
        $useMetaForXRobotsAttrId = $useMetaForXRobotsAttr->getAttributeId();
        $productXRobotsHeaderAttrId = $productXRobotsHeaderAttr->getAttributeId();
        $useCategoryXRobotsForProductsAttrId = $useCategoryXRobotsForProductsAttr->getAttributeId();
        $categoryRobotsAttrId = $categoryRobotsAttr->getAttributeId();

        $select = $connection->select()
            ->from(['cce' => $connection->getTableName('catalog_category_entity')], ['entity_id'])
            // Apply X-Robots attribute (store-specific)
            ->joinLeft(
                ['apply_xrobots_store' => $connection->getTableName('catalog_category_entity_int')],
                'apply_xrobots_store.entity_id = cce.entity_id AND apply_xrobots_store.attribute_id = ' . $applyXRobotsAttrId . ' AND apply_xrobots_store.store_id = ' . $storeId,
                []
            )
            // Apply X-Robots attribute (default)
            ->joinLeft(
                ['apply_xrobots_default' => $connection->getTableName('catalog_category_entity_int')],
                'apply_xrobots_default.entity_id = cce.entity_id AND apply_xrobots_default.attribute_id = ' . $applyXRobotsAttrId . ' AND apply_xrobots_default.store_id = 0',
                []
            )
            // X-Robots header attribute (store-specific) - text type
            ->joinLeft(
                ['xrobots_header_store' => $connection->getTableName('catalog_category_entity_text')],
                'xrobots_header_store.entity_id = cce.entity_id AND xrobots_header_store.attribute_id = ' . $xRobotsHeaderAttrId . ' AND xrobots_header_store.store_id = ' . $storeId,
                []
            )
            // X-Robots header attribute (default) - text type
            ->joinLeft(
                ['xrobots_header_default' => $connection->getTableName('catalog_category_entity_text')],
                'xrobots_header_default.entity_id = cce.entity_id AND xrobots_header_default.attribute_id = ' . $xRobotsHeaderAttrId . ' AND xrobots_header_default.store_id = 0',
                []
            )
            // Use meta for X-Robots attribute (store-specific)
            ->joinLeft(
                ['use_meta_store' => $connection->getTableName('catalog_category_entity_int')],
                'use_meta_store.entity_id = cce.entity_id AND use_meta_store.attribute_id = ' . $useMetaForXRobotsAttrId . ' AND use_meta_store.store_id = ' . $storeId,
                []
            )
            // Use meta for X-Robots attribute (default)
            ->joinLeft(
                ['use_meta_default' => $connection->getTableName('catalog_category_entity_int')],
                'use_meta_default.entity_id = cce.entity_id AND use_meta_default.attribute_id = ' . $useMetaForXRobotsAttrId . ' AND use_meta_default.store_id = 0',
                []
            )
            // Product X-Robots header attribute (store-specific) - text type
            ->joinLeft(
                ['product_xrobots_store' => $connection->getTableName('catalog_category_entity_text')],
                'product_xrobots_store.entity_id = cce.entity_id AND product_xrobots_store.attribute_id = ' . $productXRobotsHeaderAttrId . ' AND product_xrobots_store.store_id = ' . $storeId,
                []
            )
            // Product X-Robots header attribute (default) - text type
            ->joinLeft(
                ['product_xrobots_default' => $connection->getTableName('catalog_category_entity_text')],
                'product_xrobots_default.entity_id = cce.entity_id AND product_xrobots_default.attribute_id = ' . $productXRobotsHeaderAttrId . ' AND product_xrobots_default.store_id = 0',
                []
            )
            // Use category X-Robots for products attribute (store-specific)
            ->joinLeft(
                ['use_category_xrobots_store' => $connection->getTableName('catalog_category_entity_int')],
                'use_category_xrobots_store.entity_id = cce.entity_id AND use_category_xrobots_store.attribute_id = ' . $useCategoryXRobotsForProductsAttrId . ' AND use_category_xrobots_store.store_id = ' . $storeId,
                []
            )
            // Use category X-Robots for products attribute (default)
            ->joinLeft(
                ['use_category_xrobots_default' => $connection->getTableName('catalog_category_entity_int')],
                'use_category_xrobots_default.entity_id = cce.entity_id AND use_category_xrobots_default.attribute_id = ' . $useCategoryXRobotsForProductsAttrId . ' AND use_category_xrobots_default.store_id = 0',
                []
            )
            // Category robots attribute (store-specific) - for fallback when use_meta_for_x_robots is enabled
            ->joinLeft(
                ['category_robots_store' => $connection->getTableName('catalog_category_entity_int')],
                'category_robots_store.entity_id = cce.entity_id AND category_robots_store.attribute_id = ' . $categoryRobotsAttrId . ' AND category_robots_store.store_id = ' . $storeId,
                []
            )
            // Category robots attribute (default)
            ->joinLeft(
                ['category_robots_default' => $connection->getTableName('catalog_category_entity_int')],
                'category_robots_default.entity_id = cce.entity_id AND category_robots_default.attribute_id = ' . $categoryRobotsAttrId . ' AND category_robots_default.store_id = 0',
                []
            )
            ->columns([
                'x_robots_header' => new Expression(
                    'COALESCE(xrobots_header_store.value, xrobots_header_default.value)'
                ),
                'use_meta_for_x_robots' => new Expression('COALESCE(use_meta_store.value, use_meta_default.value)'),
                'product_x_robots_header' => new Expression(
                    'COALESCE(product_xrobots_store.value, product_xrobots_default.value)'
                ),
                'use_category_x_robots_for_products' => new Expression(
                    'COALESCE(use_category_xrobots_store.value, use_category_xrobots_default.value)'
                ),
                'robots_meta_tag' => new Expression(
                    'COALESCE(category_robots_store.value, category_robots_default.value)'
                )
            ])
            ->where('cce.entity_id IN (?)', $categoryIds)
            ->where('COALESCE(apply_xrobots_store.value, apply_xrobots_default.value) = ?', 1);

        return $connection->fetchAll($select);
    }
}
