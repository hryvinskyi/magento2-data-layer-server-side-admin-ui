<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api;

use Magento\Framework\App\RequestInterface;

/**
 * Reads the scope a delivery action of the settings page applies to, from its `website` parameter
 *
 * A missing, empty or `0` value is the default scope; a positive whole number is that website. Anything else is
 * refused rather than read as the default scope, which would act on every website.
 *
 * @api
 */
interface WebsiteParameterInterface
{
    /**
     * Read the website of a request
     *
     * @param RequestInterface $request
     * @return int|null Null for the default scope
     * @throws \InvalidArgumentException When the value is not a website id
     */
    public function read(RequestInterface $request): ?int;
}
