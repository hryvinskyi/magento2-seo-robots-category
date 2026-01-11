<?php
/**
 * Copyright (c) 2026. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Backend;

use Hryvinskyi\SeoRobotsApi\Api\RobotsListInterface;
use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class RobotsDirective extends AbstractBackend
{
    public function __construct(
        private readonly RobotsListInterface $robotsList
    ) {
    }

    /**
     * Before save - convert array to JSON
     */
    public function beforeSave($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $value = $object->getData($attributeCode);

        if ($value !== null && $value !== '') {
            if (is_array($value)) {
                // Validate directives
                $validation = $this->robotsList->validateDirectives($value);
                if (!$validation['valid']) {
                    throw new LocalizedException(
                        __('Invalid robots directives: %1', implode(', ', $validation['errors']))
                    );
                }

                // Encode as JSON
                $object->setData($attributeCode, json_encode($value));
            }
        }

        return parent::beforeSave($object);
    }

    /**
     * After load - convert JSON to array
     */
    public function afterLoad($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $value = $object->getData($attributeCode);

        if ($value !== null && $value !== '') {
            if (is_string($value) && $this->isJson($value)) {
                $decoded = json_decode($value, true);
                $object->setData($attributeCode, is_array($decoded) ? $decoded : []);
            }
        }

        return parent::afterLoad($object);
    }

    /**
     * Check if string is valid JSON
     */
    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
