<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\Data\ScopeIdInterfaceFactory;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\ScopeTreeInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\ScopeWebsiteInterface;

/**
 * Finds the scope in the wizard's scope tree and walks up from it to the first website: the scope itself when it is
 * one, its parent for a store view; the default scope has none
 *
 * Only scopes the tree holds are accepted, so a deleted website or store view is refused rather than read as the
 * default scope.
 *
 * @phpstan-import-type Scope from ScopeTreeInterface
 */
class ScopeWebsite implements ScopeWebsiteInterface
{
    private const WEBSITE = 'website';

    /**
     * @param ScopeTreeInterface $scopeTree
     * @param ScopeIdInterfaceFactory $scopeIdFactory
     */
    public function __construct(
        private readonly ScopeTreeInterface $scopeTree,
        private readonly ScopeIdInterfaceFactory $scopeIdFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function websiteOf(mixed $scope): int
    {
        $scopes = [];
        foreach ($this->scopeTree->getScopes() as $known) {
            $scopes[$known['id']] = $known;
        }
        $current = is_string($scope) ? ($scopes[$scope] ?? null) : null;
        if ($current === null) {
            throw new InvalidSetupRequestException(
                __('"%1" is not a scope of this store.', is_scalar($scope) ? (string) $scope : '')
            );
        }
        while ($current !== null) {
            if ($current['type'] === self::WEBSITE) {
                return $this->scopeIdFactory->create(['value' => $current['id']])->getId();
            }
            $current = $current['parent'] === null ? null : ($scopes[$current['parent']] ?? null);
        }

        return 0;
    }
}
