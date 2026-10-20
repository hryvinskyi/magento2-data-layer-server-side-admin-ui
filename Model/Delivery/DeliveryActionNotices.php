<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionNoticesInterface;
use Magento\Framework\Phrase;

/**
 * The sentences the delivery actions tell the administrator, translated, one method each
 */
class DeliveryActionNotices implements DeliveryActionNoticesInterface
{
    /**
     * @inheritDoc
     */
    public function queuedAgain(int $count): Phrase
    {
        return match (true) {
            $count === 0 => __(
                'No failed event was queued again: only events less than 72 hours old can still be sent.'
            ),
            $count === 1 => __('1 failed event is queued again.'),
            default => __('%1 failed events are queued again.', $count),
        };
    }

    /**
     * @inheritDoc
     */
    public function notQueued(): Phrase
    {
        return __('The failed events could not be queued again. The log has the details.');
    }

    /**
     * @inheritDoc
     */
    public function unknownScope(): Phrase
    {
        return __('The scope of this action is not a website. Open the settings again and retry.');
    }
}
