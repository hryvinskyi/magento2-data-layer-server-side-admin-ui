<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\DeliveryStatus;

use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryActionsScriptInterface;
use Hryvinskyi\DataLayerServerSide\Api\Data\DeliveryReportInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\DeliveryAccess;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\DeliveryStatus\RetryFailedAction;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\TemplateFactory;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use PHPUnit\Framework\TestCase;

/**
 * The "Retry failed" button shows only while the scope has failed events, and only to an administrator the retry
 * endpoint lets in; it posts the form key and the scope's website to that endpoint
 */
class RetryFailedActionTest extends TestCase
{
    /**
     * @var list<array<mixed>>
     */
    private array $created = [];

    /**
     * @var array<mixed>
     */
    private array $assigned = [];

    /**
     * @var list<string>
     */
    private array $askedResources = [];

    /**
     * Without the delivery actions' access rule nothing is rendered
     *
     * @return void
     */
    public function testHiddenWithoutAccess(): void
    {
        self::assertSame('', $this->action(false)->render($this->report(12), 1));
        self::assertSame(['Hryvinskyi_DataLayerServerSideAdminUi::delivery'], $this->askedResources);
        self::assertSame([], $this->created);
    }

    /**
     * Without failed events nothing is rendered
     *
     * @return void
     */
    public function testHiddenWithoutFailedEvents(): void
    {
        self::assertSame('', $this->action(true)->render($this->report(0), 1));
        self::assertSame([], $this->created);
    }

    /**
     * With failed events and access the button's template gets the endpoint, the form key, the website and the
     * script
     *
     * @return void
     */
    public function testRendersTheButton(): void
    {
        $action = $this->action(true);

        self::assertSame('<button/>', $action->render($this->report(3), 5));
        self::assertSame(
            [['data' => ['template' => 'Hryvinskyi_DataLayerServerSideAdminUi::system/config/retry-failed.phtml']]],
            $this->created
        );
        self::assertSame(
            '{"url":"https:\/\/admin.example.com\/hryvinskyi_datalayer_server\/delivery\/retryFailed\/",'
            . '"formKey":"Fk3y","website":5}',
            $this->assigned['config']
        );
        self::assertSame(
            '<script type="importmap">{}</script><script data-file="main.js"></script>',
            $this->assigned['script'] ?? null
        );
    }

    /**
     * At the default scope the button posts website 0, which retries every website
     *
     * @return void
     */
    public function testDefaultScopePostsWebsiteZero(): void
    {
        $this->action(true)->render($this->report(3), null);

        self::assertSame(
            '{"url":"https:\/\/admin.example.com\/hryvinskyi_datalayer_server\/delivery\/retryFailed\/",'
            . '"formKey":"Fk3y","website":0}',
            $this->assigned['config'] ?? null
        );
    }

    /**
     * Build the action
     *
     * @param bool $allowed Whether the administrator may use the delivery actions
     * @return RetryFailedAction
     */
    private function action(bool $allowed): RetryFailedAction
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(function (string $resource) use ($allowed): bool {
            $this->askedResources[] = $resource;

            return $allowed;
        });
        $block = $this->createMock(Template::class);
        $block->method('assign')->willReturnCallback(function (string $key, mixed $value) use ($block): Template {
            if ($key === 'view' && is_array($value)) {
                $this->assigned = $value;
            }

            return $block;
        });
        $block->method('toHtml')->willReturn('<button/>');
        $templates = $this->createMock(TemplateFactory::class);
        $templates->method('create')->willReturnCallback(function (array $data = []) use ($block): Template {
            $this->created[] = $data;

            return $block;
        });
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route): string => 'https://admin.example.com/' . $route . '/'
        );
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('Fk3y');
        $script = $this->createMock(DeliveryActionsScriptInterface::class);
        $script->method('render')->willReturn(
            '<script type="importmap">{}</script><script data-file="main.js"></script>'
        );

        return new RetryFailedAction(
            new DeliveryAccess($authorization),
            $templates,
            $url,
            $formKey,
            $script,
            new JsonHexTag()
        );
    }

    /**
     * Build a report with a number of failed events
     *
     * @param int $failed
     * @return DeliveryReportInterface
     */
    private function report(int $failed): DeliveryReportInterface
    {
        $report = $this->createStub(DeliveryReportInterface::class);
        $report->method('getFailedCount')->willReturn($failed);

        return $report;
    }
}
