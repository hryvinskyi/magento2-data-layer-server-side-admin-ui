<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Magento\Framework\AuthorizationInterface;

/**
 * Asks the admin's authorization whether the signed-in administrator holds the delivery actions' access rule
 */
class DeliveryAccess implements DeliveryAccessInterface
{
    /**
     * @param AuthorizationInterface $authorization
     */
    public function __construct(private readonly AuthorizationInterface $authorization)
    {
    }

    /**
     * @inheritDoc
     */
    public function isAllowed(): bool
    {
        return $this->authorization->isAllowed(self::RESOURCE);
    }
}
