<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\ScopeTreeInterface;

/**
 * A store with the default scope, website 1 holding store views 1 and 2, and website 3 holding store view 4
 */
class FixedScopeTree implements ScopeTreeInterface
{
    /**
     * @inheritDoc
     */
    public function getScopes(): array
    {
        return [
            ['id' => 'default', 'type' => 'default', 'name' => 'Default', 'code' => 'default', 'parent' => null],
            ['id' => 'website:1', 'type' => 'website', 'name' => 'Main', 'code' => 'base', 'parent' => 'default'],
            ['id' => 'store:1', 'type' => 'store', 'name' => 'English', 'code' => 'en', 'parent' => 'website:1'],
            ['id' => 'store:2', 'type' => 'store', 'name' => 'French', 'code' => 'fr', 'parent' => 'website:1'],
            ['id' => 'website:3', 'type' => 'website', 'name' => 'Trade', 'code' => 'trade', 'parent' => 'default'],
            ['id' => 'store:4', 'type' => 'store', 'name' => 'Trade', 'code' => 'trade', 'parent' => 'website:3'],
        ];
    }
}
