<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api\Data;

/**
 * What Google's validation endpoint made of one test event, free of credentials
 *
 * Exactly one of three outcomes: valid (the endpoint answered and found nothing), findings (the endpoint answered with
 * validation messages), or an error (the event was not sent, or no usable answer came back).
 *
 * @api
 */
interface TestResultInterface
{
    /**
     * Whether the endpoint accepted the event and found nothing to report
     *
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Get the endpoint's validation messages, one per finding: `<CODE> · <field>: <description>`
     *
     * @return list<string> Empty when the event is valid or no answer came back
     */
    public function getMessages(): array;

    /**
     * Get why the event could not be checked: a setting to save first, an HTTP status or a timeout
     *
     * @return string Empty when the endpoint answered
     */
    public function getError(): string;
}
