<?php
/**
 * Copyright (c) 2026. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\SeoRobotsCategory\Model\Category\Attribute\Backend;

use Hryvinskyi\SeoRobotsApi\Api\RobotsListInterface;
use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\Exception\LocalizedException;

class RobotsDirective extends AbstractBackend
{
    public function __construct(
        private readonly RobotsListInterface $robotsList
    ) {
    }

    /**
     * Before save - validate and encode as JSON
     */
    public function beforeSave($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $value = $object->getData($attributeCode);

        if ($value === null || $value === '' || $value === '[]') {
            $object->setData($attributeCode, null);
            return parent::beforeSave($object);
        }

        // Handle JSON string from form submission
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $object->setData($attributeCode, null);
                return parent::beforeSave($object);
            }
        }

        if (!is_array($value)) {
            $object->setData($attributeCode, null);
            return parent::beforeSave($object);
        }

        // Normalize and validate structured directives
        $normalized = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $directive = [
                'value' => trim((string)($item['value'] ?? '')),
                'bot' => trim((string)($item['bot'] ?? '')),
                'modification' => trim((string)($item['modification'] ?? '')),
            ];

            if ($directive['value'] !== '') {
                $normalized[] = $directive;
            }
        }

        if (empty($normalized)) {
            $object->setData($attributeCode, null);
            return parent::beforeSave($object);
        }

        // Validate structured directives
        $validation = $this->robotsList->validateStructuredDirectives($normalized);
        if (!$validation['valid']) {
            throw new LocalizedException(
                __('Invalid robots directives: %1', implode(', ', $validation['errors']))
            );
        }

        $object->setData($attributeCode, json_encode($normalized));

        return parent::beforeSave($object);
    }

    /**
     * After load - decode JSON to array
     */
    public function afterLoad($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $value = $object->getData($attributeCode);

        if ($value !== null && $value !== '') {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Ensure structured format
                    $normalized = [];
                    foreach ($decoded as $item) {
                        if (is_array($item) && isset($item['value'])) {
                            $normalized[] = [
                                'value' => $item['value'] ?? '',
                                'bot' => $item['bot'] ?? '',
                                'modification' => $item['modification'] ?? '',
                            ];
                        } elseif (is_string($item)) {
                            // Legacy format - convert
                            $normalized[] = $this->parseStringToStructured($item);
                        }
                    }
                    $object->setData($attributeCode, $normalized);
                } else {
                    $object->setData($attributeCode, []);
                }
            }
        }

        return parent::afterLoad($object);
    }

    /**
     * Parse legacy string format to structured
     */
    private function parseStringToStructured(string $str): array
    {
        $result = ['value' => '', 'bot' => '', 'modification' => ''];
        $parts = explode(':', $str);
        $advancedDirectives = ['max-snippet', 'max-image-preview', 'max-video-preview', 'unavailable_after'];

        if (count($parts) === 1) {
            $result['value'] = $parts[0];
        } elseif (count($parts) === 2) {
            $firstLower = strtolower($parts[0]);
            if (in_array($firstLower, $advancedDirectives)) {
                $result['value'] = $parts[0];
                $result['modification'] = $parts[1];
            } else {
                $result['bot'] = $parts[0];
                $result['value'] = $parts[1];
            }
        } elseif (count($parts) >= 3) {
            $result['bot'] = $parts[0];
            $result['value'] = $parts[1];
            $result['modification'] = implode(':', array_slice($parts, 2));
        }

        return $result;
    }
}
