<?php
/**
 * Copyright (c) 2025. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Model;

use Hryvinskyi\SeoRobotsCategoryApi\Api\CategoryRobotsResolverInterface;
use Hryvinskyi\SeoRobotsCategoryApi\Api\ConfigInterface;
use Hryvinskyi\SeoRobotsApi\Api\RobotsListInterface;
use Magento\Catalog\Model\Category;

/**
 * Resolves robots meta tag settings from category attributes
 */
class CategoryRobotsResolver implements CategoryRobotsResolverInterface
{
    /**
     * @inheritDoc
     * @deprecated Use getCategoryRobotsDirectives() instead
     */
    public function getCategoryRobotsCode(Category $category): ?int
    {
        $value = $category->getData(ConfigInterface::CATEGORY_ATTRIBUTE_CODE);

        if ($value === null || $value === '' || (int)$value === ConfigInterface::USE_DEFAULT) {
            return null;
        }

        return (int)$value;
    }

    /**
     * @inheritDoc
     */
    public function getCategoryRobotsDirectives(Category $category): array
    {
        $value = $category->getData(ConfigInterface::CATEGORY_ATTRIBUTE_CODE);

        if ($value === null || $value === '') {
            return [];
        }

        return $this->parseDirectivesFromValue($value);
    }

    /**
     * @inheritDoc
     */
    public function shouldApplyRobotsToProducts(Category $category): bool
    {
        $value = $category->getData(ConfigInterface::APPLY_ROBOTS_TO_PRODUCTS_ATTRIBUTE_CODE);
        return (bool)$value;
    }

    /**
     * @inheritDoc
     * @deprecated Use getProductRobotsDirectives() instead
     */
    public function getProductRobotsCode(Category $category): ?int
    {
        $value = $category->getData(ConfigInterface::PRODUCT_ROBOTS_ATTRIBUTE_CODE);

        if ($value === null || $value === '' || (int)$value === ConfigInterface::USE_DEFAULT) {
            return null;
        }

        // If set to USE_CATEGORY_ROBOTS, return the category's robots setting
        if ((int)$value === ConfigInterface::USE_CATEGORY_ROBOTS) {
            return $this->getCategoryRobotsCode($category);
        }

        return (int)$value;
    }

    /**
     * @inheritDoc
     */
    public function getProductRobotsDirectives(Category $category): array
    {
        $value = $category->getData(ConfigInterface::PRODUCT_ROBOTS_ATTRIBUTE_CODE);

        if ($value === null || $value === '') {
            return [];
        }

        // If set to USE_CATEGORY_ROBOTS, return the category's robots directives
        if ($value === (string)ConfigInterface::USE_CATEGORY_ROBOTS) {
            return $this->getCategoryRobotsDirectives($category);
        }

        return $this->parseDirectivesFromValue($value);
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

        return $map[$code] ?? [];
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

    /**
     * @inheritDoc
     */
    public function getCategoryXRobotsDirectives(Category $category): array
    {
        // If using meta robots for X-Robots, return empty to signal fallback
        if ($this->shouldUseMetaForXRobots($category)) {
            return [];
        }

        $value = $category->getData(ConfigInterface::X_ROBOTS_HEADER_ATTRIBUTE_CODE);

        if ($value === null || $value === '') {
            return [];
        }

        return $this->parseDirectivesFromValue($value);
    }

    /**
     * @inheritDoc
     */
    public function shouldUseMetaForXRobots(Category $category): bool
    {
        $value = $category->getData(ConfigInterface::USE_META_FOR_X_ROBOTS_ATTRIBUTE_CODE);
        // Default is true (1) when not set
        return $value === null || $value === '' || (bool)$value;
    }

    /**
     * @inheritDoc
     */
    public function shouldApplyXRobotsToProducts(Category $category): bool
    {
        $value = $category->getData(ConfigInterface::APPLY_X_ROBOTS_TO_PRODUCTS_ATTRIBUTE_CODE);
        return (bool)$value;
    }

    /**
     * @inheritDoc
     */
    public function getProductXRobotsDirectives(Category $category): array
    {
        // If using category X-Robots for products, return category's X-Robots directives
        if ($this->shouldUseCategoryXRobotsForProducts($category)) {
            // If category uses meta for X-Robots, return category's meta robots
            if ($this->shouldUseMetaForXRobots($category)) {
                return $this->getCategoryRobotsDirectives($category);
            }
            return $this->getCategoryXRobotsDirectives($category);
        }

        $value = $category->getData(ConfigInterface::PRODUCT_X_ROBOTS_HEADER_ATTRIBUTE_CODE);

        if ($value === null || $value === '') {
            return [];
        }

        return $this->parseDirectivesFromValue($value);
    }

    /**
     * @inheritDoc
     */
    public function shouldUseCategoryXRobotsForProducts(Category $category): bool
    {
        $value = $category->getData(ConfigInterface::USE_CATEGORY_X_ROBOTS_FOR_PRODUCTS_ATTRIBUTE_CODE);
        // Default is true (1) when not set
        return $value === null || $value === '' || (bool)$value;
    }

    /**
     * @inheritDoc
     */
    public function shouldUseCategoryRobotsForProducts(Category $category): bool
    {
        $value = $category->getData(ConfigInterface::USE_CATEGORY_ROBOTS_FOR_PRODUCTS_ATTRIBUTE_CODE);
        // Default is true (1) when not set
        return $value === null || $value === '' || (bool)$value;
    }
}
