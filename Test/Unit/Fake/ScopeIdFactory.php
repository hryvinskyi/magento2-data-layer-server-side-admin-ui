<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\Data\ScopeIdInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\Data\ScopeIdInterfaceFactory;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\Data\ScopeId;

/**
 * Builds the wizard's scope ids without an object manager
 */
class ScopeIdFactory extends ScopeIdInterfaceFactory
{
    /**
     * Build without an object manager
     */
    public function __construct()
    {
    }

    /**
     * Build a scope id from its text
     *
     * @param array{value?: string} $data
     * @return ScopeIdInterface
     */
    public function create(array $data = [])
    {
        return new ScopeId($data['value'] ?? '');
    }
}
