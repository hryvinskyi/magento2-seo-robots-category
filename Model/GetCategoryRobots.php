<?php
/**
 * Copyright (c) 2026. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Model;

use Hryvinskyi\SeoRobotsCategoryApi\Api\CategoryRobotsResolverInterface;
use Hryvinskyi\SeoRobotsCategoryApi\Api\CategoryRobotsRepositoryInterface;
use Hryvinskyi\SeoRobotsCategoryApi\Api\ConfigInterface;
use Hryvinskyi\SeoRobotsCategoryApi\Api\GetCategoryRobotsInterface;
use Hryvinskyi\SeoRobotsApi\Api\RobotsListInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;

class GetCategoryRobots implements GetCategoryRobotsInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly RobotsListInterface $robotsList,
        private readonly CategoryRobotsResolverInterface $categoryRobotsResolver,
        private readonly CategoryRobotsRepositoryInterface $categoryRobotsRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Category $category): ?string
    {
        if (!$this->config->isEnabled()) {
            return null;
        }

        $directives = $this->categoryRobotsResolver->getCategoryRobotsDirectives($category);

        if (empty($directives)) {
            return null;
        }

        return $this->robotsList->buildMetaRobotsFromDirectives($directives);
    }

    /**
     * @inheritDoc
     */
    public function executeForProduct(Product $product): ?string
    {
        if (!$this->config->isEnabled()) {
            return null;
        }

        $categoryIds = $product->getCategoryIds();

        if (empty($categoryIds)) {
            return null;
        }

        // Fetch category robots data via direct SQL query with store scope
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return null;
        }

        $categoriesData = $this->categoryRobotsRepository->getProductRobotsDataByCategoryIds($categoryIds, $storeId);

        if (empty($categoriesData)) {
            return null;
        }

        // Check each category for product robots settings
        // Priority: NOINDEX directives take precedence
        $finalDirectives = null;

        foreach ($categoriesData as $categoryData) {
            $productRobotsValue = $categoryData['product_robots_meta_tag'];

            // Handle "Use Category Robots" option
            if ($productRobotsValue === (string)ConfigInterface::USE_CATEGORY_ROBOTS) {
                $productRobotsValue = $categoryData['robots_meta_tag'];
            }

            // Skip if null or use default
            if ($productRobotsValue === null || $productRobotsValue === '') {
                continue;
            }

            // Parse directives from JSON
            $directives = $this->parseDirectivesFromValue($productRobotsValue);

            if (empty($directives)) {
                continue;
            }

            // If we found NOINDEX directive, use it immediately (highest priority)
            if (in_array('noindex', $directives)) {
                return $this->robotsList->buildMetaRobotsFromDirectives($directives);
            }

            // Store the first non-NOINDEX directives we find
            if ($finalDirectives === null) {
                $finalDirectives = $directives;
            }
        }

        return $finalDirectives !== null ? $this->robotsList->buildMetaRobotsFromDirectives($finalDirectives) : null;
    }

    /**
     * Parse directives from attribute value (JSON string or legacy integer code)
     *
     * @param mixed $value
     * @return array
     */
    private function parseDirectivesFromValue($value): array
    {
        // Handle JSON directive array (new format)
        if (is_string($value) && $this->isJson($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        // Handle legacy integer codes (backward compatibility)
        if (is_numeric($value)) {
            return $this->convertCodeToDirectives((int)$value);
        }

        // Handle already parsed array
        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    /**
     * Convert legacy integer code to directive array
     *
     * @param int $code
     * @return array
     */
    private function convertCodeToDirectives(int $code): array
    {
        $map = [
            RobotsListInterface::NOINDEX_NOFOLLOW => ['noindex', 'nofollow'],
            RobotsListInterface::NOINDEX_FOLLOW => ['noindex', 'follow'],
            RobotsListInterface::INDEX_NOFOLLOW => ['index', 'nofollow'],
            RobotsListInterface::INDEX_FOLLOW => ['index', 'follow'],
            RobotsListInterface::NOINDEX_NOFOLLOW_NOARCHIVE => ['noindex', 'nofollow', 'noarchive'],
            RobotsListInterface::NOINDEX_FOLLOW_NOARCHIVE => ['noindex', 'follow', 'noarchive'],
            RobotsListInterface::INDEX_NOFOLLOW_NOARCHIVE => ['index', 'nofollow', 'noarchive'],
            RobotsListInterface::INDEX_FOLLOW_NOARCHIVE => ['index', 'follow', 'noarchive'],
        ];

        return $map[$code] ?? ['index', 'follow'];
    }

    /**
     * Check if string is valid JSON
     *
     * @param string $string
     * @return bool
     */
    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
