<?php
/**
 * Copyright (c) 2026. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Setup\Patch\Data;

use Hryvinskyi\SeoRobotsCategoryApi\Api\ConfigInterface;
use Hryvinskyi\SeoRobotsApi\Api\RobotsListInterface;
use Magento\Catalog\Model\Category;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\ResourceConnection;

class MigrateCategoryRobotsToDirectives implements DataPatchInterface
{
    /**
     * Mapping of old integer codes to new directive arrays
     */
    private const CODE_TO_DIRECTIVES_MAP = [
        RobotsListInterface::NOINDEX_NOFOLLOW => ['noindex', 'nofollow'],
        RobotsListInterface::NOINDEX_FOLLOW => ['noindex', 'follow'],
        RobotsListInterface::INDEX_NOFOLLOW => ['index', 'nofollow'],
        RobotsListInterface::INDEX_FOLLOW => ['index', 'follow'],
        RobotsListInterface::NOINDEX_NOFOLLOW_NOARCHIVE => ['noindex', 'nofollow', 'noarchive'],
        RobotsListInterface::NOINDEX_FOLLOW_NOARCHIVE => ['noindex', 'follow', 'noarchive'],
        RobotsListInterface::INDEX_NOFOLLOW_NOARCHIVE => ['index', 'nofollow', 'noarchive'],
        RobotsListInterface::INDEX_FOLLOW_NOARCHIVE => ['index', 'follow', 'noarchive'],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Step 1: Change attribute types from int to text
        $this->updateAttributeTypes($eavSetup);

        // Step 2: Migrate existing category data
        $this->migrateCategoryData();

        return $this;
    }

    /**
     * Update attribute types from int to text for JSON storage
     */
    private function updateAttributeTypes(EavSetup $eavSetup): void
    {
        // Update robots_meta_tag attribute
        $eavSetup->updateAttribute(
            Category::ENTITY,
            ConfigInterface::CATEGORY_ATTRIBUTE_CODE,
            [
                'backend_type' => 'text',
                'backend_model' => \Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Backend\RobotsDirective::class,
            ]
        );

        // Update product_robots_meta_tag attribute
        $eavSetup->updateAttribute(
            Category::ENTITY,
            ConfigInterface::PRODUCT_ROBOTS_ATTRIBUTE_CODE,
            [
                'backend_type' => 'text',
                'backend_model' => \Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Backend\RobotsDirective::class,
            ]
        );
    }

    /**
     * Migrate existing category data from integer codes to JSON directive arrays
     */
    private function migrateCategoryData(): void
    {
        $connection = $this->resourceConnection->getConnection();

        // Get attribute IDs
        $robotsAttributeId = $this->getAttributeId(ConfigInterface::CATEGORY_ATTRIBUTE_CODE);
        $productRobotsAttributeId = $this->getAttributeId(ConfigInterface::PRODUCT_ROBOTS_ATTRIBUTE_CODE);

        if (!$robotsAttributeId || !$productRobotsAttributeId) {
            return;
        }

        // Migrate robots_meta_tag values
        $this->migrateAttributeValues($connection, 'catalog_category_entity_int', 'catalog_category_entity_text', $robotsAttributeId);

        // Migrate product_robots_meta_tag values
        $this->migrateAttributeValues($connection, 'catalog_category_entity_int', 'catalog_category_entity_text', $productRobotsAttributeId);
    }

    /**
     * Get attribute ID by code
     */
    private function getAttributeId(string $attributeCode): ?int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('eav_attribute'), ['attribute_id'])
            ->where('attribute_code = ?', $attributeCode)
            ->where('entity_type_id = ?', $this->getCategoryEntityTypeId());

        $attributeId = $connection->fetchOne($select);
        return $attributeId ? (int)$attributeId : null;
    }

    /**
     * Get category entity type ID
     */
    private function getCategoryEntityTypeId(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('eav_entity_type'), ['entity_type_id'])
            ->where('entity_type_code = ?', Category::ENTITY);

        return (int)$connection->fetchOne($select);
    }

    /**
     * Migrate attribute values from int table to text table with directive conversion
     */
    private function migrateAttributeValues(
        $connection,
        string $sourceTable,
        string $targetTable,
        int $attributeId
    ): void {
        $sourceTableName = $this->resourceConnection->getTableName($sourceTable);
        $targetTableName = $this->resourceConnection->getTableName($targetTable);

        // Get all existing values
        $select = $connection->select()
            ->from($sourceTableName)
            ->where('attribute_id = ?', $attributeId);

        $rows = $connection->fetchAll($select);

        foreach ($rows as $row) {
            $oldValue = (int)$row['value'];

            // Skip if value is already 0 (use default) or -1 (use category robots)
            if ($oldValue === ConfigInterface::USE_DEFAULT || $oldValue === ConfigInterface::USE_CATEGORY_ROBOTS) {
                continue;
            }

            // Convert to directive array
            $directives = self::CODE_TO_DIRECTIVES_MAP[$oldValue] ?? [];
            $newValue = json_encode($directives);

            // Insert into text table
            $connection->insertOnDuplicate(
                $targetTableName,
                [
                    'attribute_id' => $attributeId,
                    'store_id' => $row['store_id'],
                    'entity_id' => $row['entity_id'],
                    'value' => $newValue,
                ],
                ['value']
            );

            // Delete from int table
            $connection->delete(
                $sourceTableName,
                [
                    'attribute_id = ?' => $attributeId,
                    'store_id = ?' => $row['store_id'],
                    'entity_id = ?' => $row['entity_id'],
                ]
            );
        }
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
