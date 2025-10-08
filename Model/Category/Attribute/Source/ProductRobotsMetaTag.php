<?php
/**
 * Copyright (c) 2025. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Source;

use Hryvinskyi\SeoRobotsCategoryApi\Api\ConfigInterface;
use Hryvinskyi\SeoRobotsApi\Api\RobotsListInterface;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class ProductRobotsMetaTag extends AbstractSource
{
    /**
     * @inheritDoc
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => ConfigInterface::USE_CATEGORY_ROBOTS, 'label' => __('Use Category Robots')],
                ['value' => RobotsListInterface::INDEX_FOLLOW, 'label' => __('INDEX, FOLLOW')],
                ['value' => RobotsListInterface::NOINDEX_FOLLOW, 'label' => __('NOINDEX, FOLLOW')],
                ['value' => RobotsListInterface::INDEX_NOFOLLOW, 'label' => __('INDEX, NOFOLLOW')],
                ['value' => RobotsListInterface::NOINDEX_NOFOLLOW, 'label' => __('NOINDEX, NOFOLLOW')],
                ['value' => RobotsListInterface::INDEX_FOLLOW_NOARCHIVE, 'label' => __('INDEX, FOLLOW, NOARCHIVE')],
                ['value' => RobotsListInterface::NOINDEX_FOLLOW_NOARCHIVE, 'label' => __('NOINDEX, FOLLOW, NOARCHIVE')],
                ['value' => RobotsListInterface::INDEX_NOFOLLOW_NOARCHIVE, 'label' => __('INDEX, NOFOLLOW, NOARCHIVE')],
                ['value' => RobotsListInterface::NOINDEX_NOFOLLOW_NOARCHIVE, 'label' => __('NOINDEX, NOFOLLOW, NOARCHIVE')],
            ];
        }
        return $this->_options;
    }
}
