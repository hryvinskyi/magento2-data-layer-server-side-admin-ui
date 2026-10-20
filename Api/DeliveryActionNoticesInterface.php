<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api;

use Magento\Framework\Phrase;

/**
 * The sentences the delivery actions tell the administrator, translated, one method each
 *
 * @api
 */
interface DeliveryActionNoticesInterface
{
    /**
     * Report how many failed events were actually queued again (older events stay failed)
     *
     * @param int $count
     * @return Phrase
     */
    public function queuedAgain(int $count): Phrase;

    /**
     * Report that the failed events could not be queued again
     *
     * @return Phrase
     */
    public function notQueued(): Phrase;

    /**
     * Report a scope the action does not know
     *
     * @return Phrase
     */
    public function unknownScope(): Phrase;
}
