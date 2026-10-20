<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup\ScopeWebsite;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\FixedScopeTree;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\ScopeIdFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A website uses its own server-side settings, a store view its website's, the default scope the default ones; a
 * scope the store does not have is refused
 */
class ScopeWebsiteTest extends TestCase
{
    /**
     * The website of each kind of scope
     *
     * @dataProvider scopes
     * @param string $scope
     * @param int $websiteId
     * @return void
     */
    #[DataProvider('scopes')]
    public function testWebsiteOfAScope(string $scope, int $websiteId): void
    {
        self::assertSame($websiteId, $this->scopeWebsite()->websiteOf($scope));
    }

    /**
     * Scopes and their websites
     *
     * @return array<string, array{string, int}>
     */
    public static function scopes(): array
    {
        return [
            'default scope' => ['default', 0],
            'website' => ['website:3', 3],
            'store view' => ['store:2', 1],
            'store view of another website' => ['store:4', 3],
        ];
    }

    /**
     * A scope id of a website or store view the store does not have, or no scope id at all, is refused
     *
     * @return void
     */
    public function testUnknownScopesAreRefused(): void
    {
        foreach (['website:9', 'store:9', 'shop:1', '', null, 3] as $scope) {
            try {
                $this->scopeWebsite()->websiteOf($scope);
                self::fail('The scope is refused: ' . (string) json_encode($scope));
            } catch (InvalidSetupRequestException $refusal) {
                self::assertStringContainsString('is not a scope of this store.', $refusal->getMessage());
            }
        }
    }

    /**
     * Build the lookup over a fixed scope tree
     *
     * @return ScopeWebsite
     */
    private function scopeWebsite(): ScopeWebsite
    {
        return new ScopeWebsite(new FixedScopeTree(), new ScopeIdFactory());
    }
}
