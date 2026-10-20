<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake;

use Hryvinskyi\DataLayerServerSide\Api\Data\DeliveryReportInterface;
use Hryvinskyi\DataLayerServerSide\Api\DeliveryStatusInterface;

/**
 * Answers every status question with one prepared report, and remembers which website was asked about
 */
class FixedDeliveryStatus implements DeliveryStatusInterface
{
    /**
     * @var list<int|null>
     */
    public array $askedWebsites = [];

    /**
     * @param DeliveryReportInterface $report
     */
    public function __construct(private readonly DeliveryReportInterface $report)
    {
    }

    /**
     * @inheritDoc
     */
    public function forWebsite(?int $websiteId): DeliveryReportInterface
    {
        $this->askedWebsites[] = $websiteId;

        return $this->report;
    }

    /**
     * @inheritDoc
     */
    public function perWebsite(): array
    {
        return [$this->report];
    }

    /**
     * @inheritDoc
     */
    public function overview(array $websiteIds): array
    {
        $this->askedWebsites[] = null;
        array_push($this->askedWebsites, ...$websiteIds);

        return ['all' => $this->report, 'websites' => array_fill_keys($websiteIds, $this->report)];
    }
}
