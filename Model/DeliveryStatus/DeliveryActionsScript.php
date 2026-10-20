<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\DeliveryStatus;

use Hryvinskyi\DataLayerAdminUi\Api\ModuleScriptInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionsScriptInterface;

/**
 * The delivery actions' entry with the names its modules import one another by, printed with the first button a page
 * renders; the entry is a module script, which runs once the page is parsed, so it still finds the buttons rendered
 * after it. The service is shared within a request, which is one page.
 */
class DeliveryActionsScript implements DeliveryActionsScriptInterface
{
    private const ENTRY_FILE_ID = 'Hryvinskyi_DataLayerServerSideAdminUi::js/delivery-actions/main.js';
    private const PREFIX = '@hryvinskyi/data-layer-server-side-admin/delivery-actions/';
    private const DIRECTORY = 'Hryvinskyi_DataLayerServerSideAdminUi::js/delivery-actions/';
    private const MODULES = ['retry-failed.js', 'test-event.js', 'action-config.js', 'test-result.js'];

    /**
     * @var bool Whether this page's tags were rendered already
     */
    private bool $rendered = false;

    /**
     * @param ModuleScriptInterface $moduleScript
     */
    public function __construct(private readonly ModuleScriptInterface $moduleScript)
    {
    }

    /**
     * @inheritDoc
     */
    public function render(): string
    {
        if ($this->rendered) {
            return '';
        }
        $imports = [];
        foreach (self::MODULES as $module) {
            $imports[self::PREFIX . $module] = self::DIRECTORY . $module;
        }

        $tags = $this->moduleScript->render(self::ENTRY_FILE_ID, $imports);
        $this->rendered = true;

        return $tags;
    }
}
