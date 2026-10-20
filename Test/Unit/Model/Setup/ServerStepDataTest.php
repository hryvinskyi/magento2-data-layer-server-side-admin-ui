<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Setup;

use Hryvinskyi\DataLayerServerSide\Api\Data\DeliveryReportInterface;
use Hryvinskyi\DataLayerServerSide\Api\DeliveryStatusInterface;
use Hryvinskyi\DataLayerServerSide\Model\Data\DeliveryReport;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryAccessInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusViewInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\TestableTransports;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup\ServerStepData;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\FixedScopeTree;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\ScopeIdFactory;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\Settings;
use Magento\Backend\Model\UrlInterface;
use PHPUnit\Framework\TestCase;

/**
 * The step's data: the URLs of its actions, whether the administrator may use them, the testable transports, the
 * settings and glyph of each transport, and the delivery status of the default scope and of every website (never of
 * a store view, which shows its website's), read in one overview rather than website by website
 */
class ServerStepDataTest extends TestCase
{
    /**
     * The websites each delivery overview was asked for
     *
     * @var list<list<int>>
     */
    private array $overviews = [];

    /**
     * Everything the step reads, with the status keyed by scope id
     *
     * @return void
     */
    public function testBuildsTheStepData(): void
    {
        $data = $this->stepData(
            ['ga4' => ['mp_id', 'api_secret'], 'sgtm' => ['endpoint']],
            ['ga4' => 'analytics']
        )->get();

        self::assertSame('https://admin.test/hryvinskyi_datalayer_server/setup/testEvent', $data['testUrl']);
        self::assertSame('https://admin.test/hryvinskyi_datalayer_server/setup/retryFailed', $data['retryUrl']);
        self::assertTrue($data['canAct']);
        self::assertSame(['ga4'], $data['testableTransports']);
        self::assertSame(['ga4' => ['mp_id', 'api_secret'], 'sgtm' => ['endpoint']], $data['transportFields']);
        self::assertSame(['ga4' => 'analytics'], $data['transportIcons']);
        self::assertSame(['default', 'website:1', 'website:3'], array_keys($data['status']));
        self::assertSame('every website', $data['status']['default']['progress']);
        self::assertSame('website 1', $data['status']['website:1']['progress']);
        self::assertSame('website 3', $data['status']['website:3']['progress']);
        self::assertSame([[1, 3]], $this->overviews);
    }

    /**
     * Empty maps encode as JSON objects
     *
     * @return void
     */
    public function testEmptyMapsAreObjects(): void
    {
        $data = $this->stepData([], [])->get();

        self::assertSame('{}', json_encode($data['transportFields']));
        self::assertSame('{}', json_encode($data['transportIcons']));
    }

    /**
     * A transport may show only settings of the server-side step; a mistyped or foreign key fails loudly
     *
     * @return void
     */
    public function testForeignSettingsAreRefused(): void
    {
        foreach ([['ga4' => ['enabled']], ['ga4' => ['no_such_setting']], ['ga4' => 'mp_id']] as $fields) {
            try {
                $this->stepData($fields, []);
                self::fail('The list is refused: ' . (string) json_encode($fields));
            } catch (\InvalidArgumentException $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }
    }

    /**
     * Build the step data over fakes
     *
     * @param array<mixed> $fields
     * @param array<mixed> $icons
     * @return ServerStepData
     */
    private function stepData(array $fields, array $icons): ServerStepData
    {
        $urls = $this->createStub(UrlInterface::class);
        $urls->method('getUrl')
            ->willReturnCallback(static fn (string $route): string => 'https://admin.test/' . $route);
        $access = $this->createStub(DeliveryAccessInterface::class);
        $access->method('isAllowed')->willReturn(true);
        $status = $this->createMock(DeliveryStatusInterface::class);
        $status->expects(self::never())->method('forWebsite');
        $status->method('overview')->willReturnCallback(function (array $websiteIds): array {
            $asked = [];
            $websites = [];
            foreach ($websiteIds as $websiteId) {
                self::assertIsInt($websiteId);
                $asked[] = $websiteId;
                $websites[$websiteId] = new DeliveryReport($websiteId);
            }
            $this->overviews[] = $asked;

            return ['all' => new DeliveryReport(null), 'websites' => $websites];
        });
        $view = $this->createStub(DeliveryStatusViewInterface::class);
        $view->method('describe')->willReturnCallback(static fn (DeliveryReportInterface $report): array => [
            'pending' => 0,
            'sent' => 0,
            'failed' => 0,
            'progress' => $report->getWebsiteId() === null ? 'every website' : 'website ' . $report->getWebsiteId(),
            'failedLine' => '',
        ]);

        return new ServerStepData(
            $urls,
            $access,
            new TestableTransports(['ga4']),
            $status,
            $view,
            new FixedScopeTree(),
            new ScopeIdFactory(),
            Settings::descriptors(),
            $fields,
            $icons
        );
    }
}
