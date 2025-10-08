<?php
/**
 * Copyright (c) 2025. All rights reserved.
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
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var RobotsListInterface
     */
    private $robotsList;

    /**
     * @var CategoryRobotsResolverInterface
     */
    private $categoryRobotsResolver;

    /**
     * @var CategoryRobotsRepositoryInterface
     */
    private $categoryRobotsRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ConfigInterface $config
     * @param RobotsListInterface $robotsList
     * @param CategoryRobotsResolverInterface $categoryRobotsResolver
     * @param CategoryRobotsRepositoryInterface $categoryRobotsRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ConfigInterface $config,
        RobotsListInterface $robotsList,
        CategoryRobotsResolverInterface $categoryRobotsResolver,
        CategoryRobotsRepositoryInterface $categoryRobotsRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->robotsList = $robotsList;
        $this->categoryRobotsResolver = $categoryRobotsResolver;
        $this->categoryRobotsRepository = $categoryRobotsRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function execute(Category $category): ?string
    {
        if (!$this->config->isEnabled()) {
            return null;
        }

        $robotsCode = $this->categoryRobotsResolver->getCategoryRobotsCode($category);

        if ($robotsCode === null) {
            return null;
        }

        return $this->robotsList->getMetaRobotsByCode($robotsCode);
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
        // Priority: NOINDEX settings take precedence
        $robotsCode = null;

        foreach ($categoriesData as $categoryData) {
            $productRobotsCode = $categoryData['product_robots_meta_tag'];

            // Handle "Use Category Robots" option
            if ((int)$productRobotsCode === ConfigInterface::USE_CATEGORY_ROBOTS) {
                $productRobotsCode = $categoryData['robots_meta_tag'];
            }

            // Skip if null or use default
            if ($productRobotsCode === null || (int)$productRobotsCode === ConfigInterface::USE_DEFAULT) {
                continue;
            }

            $productRobotsCode = (int)$productRobotsCode;

            // If we found a NOINDEX setting, use it immediately (highest priority)
            if (in_array($productRobotsCode, [
                RobotsListInterface::NOINDEX_NOFOLLOW,
                RobotsListInterface::NOINDEX_FOLLOW,
                RobotsListInterface::NOINDEX_NOFOLLOW_NOARCHIVE,
                RobotsListInterface::NOINDEX_FOLLOW_NOARCHIVE
            ])) {
                return $this->robotsList->getMetaRobotsByCode($productRobotsCode);
            }

            // Store the first robots code we find
            if ($robotsCode === null) {
                $robotsCode = $productRobotsCode;
            }
        }

        return $robotsCode !== null ? $this->robotsList->getMetaRobotsByCode($robotsCode) : null;
    }
}
