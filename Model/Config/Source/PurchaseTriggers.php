<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Config\Source;

use Hryvinskyi\DataLayerServerSide\Model\Config\PurchaseTrigger;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * The moments an order's purchase can be reported
 */
class PurchaseTriggers implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return list<array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return array_map(
            static fn (PurchaseTrigger $trigger): array => [
                'value' => $trigger->value,
                'label' => match ($trigger) {
                    PurchaseTrigger::Paid => __('The order is paid (invoice)'),
                    PurchaseTrigger::Placed => __('The order is placed'),
                },
            ],
            PurchaseTrigger::cases()
        );
    }
}
