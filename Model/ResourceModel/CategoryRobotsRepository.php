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

        $applyRobotsAttrId = $applyRobotsAttr->getAttributeId();
        $productRobotsAttrId = $productRobotsAttr->getAttributeId();
        $categoryRobotsAttrId = $categoryRobotsAttr->getAttributeId();

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
            // Product robots attribute (store-specific)
            ->joinLeft(
                ['product_robots_store' => $connection->getTableName('catalog_category_entity_int')],
                'product_robots_store.entity_id = cce.entity_id AND product_robots_store.attribute_id = ' . $productRobotsAttrId . ' AND product_robots_store.store_id = ' . $storeId,
                []
            )
            // Product robots attribute (default)
            ->joinLeft(
                ['product_robots_default' => $connection->getTableName('catalog_category_entity_int')],
                'product_robots_default.entity_id = cce.entity_id AND product_robots_default.attribute_id = ' . $productRobotsAttrId . ' AND product_robots_default.store_id = 0',
                []
            )
            // Category robots attribute (store-specific)
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
                'product_robots_meta_tag' => new Expression('COALESCE(product_robots_store.value, product_robots_default.value)'),
                'robots_meta_tag' => new Expression('COALESCE(category_robots_store.value, category_robots_default.value)')
            ])
            ->where('cce.entity_id IN (?)', $categoryIds)
            ->where('COALESCE(apply_robots_store.value, apply_robots_default.value) = ?', 1);

        return $connection->fetchAll($select);
    }
}
