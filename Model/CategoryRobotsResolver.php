<?php
/**
 * Copyright (c) 2025. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Model;

use Hryvinskyi\SeoRobotsCategoryApi\Api\CategoryRobotsResolverInterface;
use Hryvinskyi\SeoRobotsCategoryApi\Api\ConfigInterface;
use Magento\Catalog\Model\Category;

/**
 * Resolves robots meta tag settings from category attributes
 */
class CategoryRobotsResolver implements CategoryRobotsResolverInterface
{
    /**
     * @inheritDoc
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
    public function shouldApplyRobotsToProducts(Category $category): bool
    {
        $value = $category->getData(ConfigInterface::APPLY_ROBOTS_TO_PRODUCTS_ATTRIBUTE_CODE);
        return (bool)$value;
    }

    /**
     * @inheritDoc
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
}
