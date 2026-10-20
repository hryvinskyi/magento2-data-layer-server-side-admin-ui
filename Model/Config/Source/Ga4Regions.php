<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Config\Source;

use Hryvinskyi\DataLayerServerSide\Model\Config\Ga4Region;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * The regions of Google's collection endpoint
 */
class Ga4Regions implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return list<array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return array_map(
            static fn (Ga4Region $region): array => [
                'value' => $region->value,
                'label' => match ($region) {
                    Ga4Region::Global => __('Global (%1)', $region->host()),
                    Ga4Region::Eu => __('EU only (%1)', $region->host()),
                },
            ],
            Ga4Region::cases()
        );
    }
}
