<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Saves a whole number within bounds given by its virtual type
 */
class IntegerInRange extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param int $minimum The smallest number accepted
     * @param int $maximum The largest number accepted
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly int $minimum,
        private readonly int $maximum,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Refuse a value that is not a whole number within the bounds
     *
     * @return $this
     * @throws LocalizedException When the value is not a whole number from the minimum to the maximum
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $number = filter_var(
            is_scalar($value) ? trim((string) $value) : '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => $this->minimum, 'max_range' => $this->maximum]]
        );
        if (!is_int($number)) {
            throw new LocalizedException(
                __('Enter a whole number from %1 to %2.', $this->minimum, $this->maximum)
            );
        }
        $this->setValue((string) $number);

        return parent::beforeSave();
    }
}
