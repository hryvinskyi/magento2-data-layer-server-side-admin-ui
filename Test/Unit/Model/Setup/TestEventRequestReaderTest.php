<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\Http\JsonBodyInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup\ScopeWebsite;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup\TestEventDraft;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup\TestEventRequestReader;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\FixedScopeTree;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\ScopeIdFactory;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\Settings;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;

/**
 * A test request names the scope and the settings to draft; the scope becomes its website, the settings their
 * drafted values, and a body of another shape is refused
 */
class TestEventRequestReaderTest extends TestCase
{
    /**
     * A store view's request tests its website with the drafted settings
     *
     * @return void
     */
    public function testReadsTheWebsiteAndTheDraft(): void
    {
        $request = $this->reader(['scope' => 'store:1', 'settings' => ['mp_id' => 'g-1']])
            ->read($this->createStub(RequestInterface::class));

        self::assertSame(
            ['websiteId' => 1, 'draft' => ['hryvinskyi_datalayer_server/ga4/measurement_id' => 'G-1']],
            $request
        );
    }

    /**
     * Empty settings test the saved ones
     *
     * @return void
     */
    public function testEmptySettingsDraftNothing(): void
    {
        $request = $this->reader(['scope' => 'default', 'settings' => []])
            ->read($this->createStub(RequestInterface::class));

        self::assertSame(['websiteId' => 0, 'draft' => []], $request);
    }

    /**
     * Settings that are not an object, or are missing, are refused
     *
     * @return void
     */
    public function testSettingsMustBeAnObject(): void
    {
        foreach ([['scope' => 'default'], ['scope' => 'default', 'settings' => ['a', 'b']]] as $body) {
            try {
                $this->reader($body)->read($this->createStub(RequestInterface::class));
                self::fail('The body is refused: ' . (string) json_encode($body));
            } catch (InvalidSetupRequestException $refusal) {
                self::assertSame(
                    'The test event needs its settings as an object keyed by setting.',
                    $refusal->getMessage()
                );
            }
        }
    }

    /**
     * Build the reader over a body
     *
     * @param array<mixed> $body
     * @return TestEventRequestReader
     */
    private function reader(array $body): TestEventRequestReader
    {
        $jsonBody = $this->createStub(JsonBodyInterface::class);
        $jsonBody->method('read')->willReturn($body);

        return new TestEventRequestReader(
            $jsonBody,
            new ScopeWebsite(new FixedScopeTree(), new ScopeIdFactory()),
            new TestEventDraft(Settings::descriptors(), Settings::kinds())
        );
    }
}
