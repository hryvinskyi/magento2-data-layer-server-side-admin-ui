<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\Http\JsonBodyInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\ScopeWebsiteInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\TestEventDraftInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\TestEventRequestReaderInterface;
use Magento\Framework\App\RequestInterface;

/**
 * Reads the JSON body, finds the scope's website and drafts the settings; `settings` must be an object (an empty one
 * tests the saved settings)
 */
class TestEventRequestReader implements TestEventRequestReaderInterface
{
    /**
     * @param JsonBodyInterface $jsonBody
     * @param ScopeWebsiteInterface $scopeWebsite
     * @param TestEventDraftInterface $draft
     */
    public function __construct(
        private readonly JsonBodyInterface $jsonBody,
        private readonly ScopeWebsiteInterface $scopeWebsite,
        private readonly TestEventDraftInterface $draft
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(RequestInterface $request): array
    {
        $body = $this->jsonBody->read($request);
        $websiteId = $this->scopeWebsite->websiteOf($body['scope'] ?? null);
        $settings = $body['settings'] ?? null;
        if (!is_array($settings) || ($settings !== [] && array_is_list($settings))) {
            throw new InvalidSetupRequestException(
                __('The test event needs its settings as an object keyed by setting.')
            );
        }

        return ['websiteId' => $websiteId, 'draft' => $this->draft->toDraft($settings)];
    }
}
