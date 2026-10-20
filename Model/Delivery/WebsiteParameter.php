<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\WebsiteParameterInterface;
use Magento\Framework\App\RequestInterface;

/**
 * Reads the scope a delivery action applies to from the `website` parameter the settings page sends
 *
 * A missing, empty or `0` value is the default scope; a positive whole number is that website. Anything else is
 * refused rather than read as the default scope, which would act on every website.
 */
class WebsiteParameter implements WebsiteParameterInterface
{
    private const NAME = 'website';

    /**
     * @inheritDoc
     */
    public function read(RequestInterface $request): ?int
    {
        $value = $request->getParam(self::NAME);
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9]\d{0,9}$/D', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('The website parameter is not a website id.');
    }
}
