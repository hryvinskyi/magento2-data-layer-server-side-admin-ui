<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Api;

/**
 * Renders the tags that load the delivery actions' script, which binds every "Retry failed" and "Send test event"
 * button on the settings page
 *
 * A page carries the tags once, however many buttons it shows: a second import map in one page is an error in
 * browsers that accept only one, and the entry binds every button it finds.
 */
interface DeliveryActionsScriptInterface
{
    /**
     * Render the import map of the delivery actions' modules and their entry, the first time a page asks
     *
     * @return string Empty when the tags were already rendered for this page
     * @throws \InvalidArgumentException When the import map cannot be written as JSON
     * @throws \Magento\Framework\Exception\LocalizedException When a module's file cannot be resolved
     */
    public function render(): string;
}
