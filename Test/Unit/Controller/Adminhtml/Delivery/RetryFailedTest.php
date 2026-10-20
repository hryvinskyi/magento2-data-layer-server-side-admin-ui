<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Controller\Adminhtml\Delivery;

use Hryvinskyi\DataLayerAdminUi\Model\Form\SettingsGroupLink;
use Hryvinskyi\DataLayerServerSide\Api\FailedEventRetryInterface;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\RecordingLogger;
use Hryvinskyi\DataLayerServerSideAdminUi\Controller\Adminhtml\Delivery\RetryFailed;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\DeliveryActionNotices;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\RetryFailedEvents;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\WebsiteParameter;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The retry endpoint reads the scope, delegates to the retry service, and returns to the settings with a message
 */
class RetryFailedTest extends TestCase
{
    /**
     * @var list<int|null>
     */
    private array $retried = [];

    /**
     * @var list<array{string, string}>
     */
    private array $messages = [];

    /**
     * @var list<array{string, array<mixed>}>
     */
    private array $redirects = [];

    /**
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    /**
     * At a website only that website is retried, and the page returns to that website's server-side settings
     *
     * @return void
     */
    public function testRetriesTheWebsiteAndReturnsToIt(): void
    {
        $this->controller('2', static fn (): int => 12)->execute();

        self::assertSame([2], $this->retried);
        self::assertSame([['success', '12 failed events are queued again.']], $this->messages);
        self::assertSame(
            [[
                'adminhtml/system_config/edit',
                [
                    'section' => 'hryvinskyi_datalayer',
                    'website' => 2,
                    '_fragment' => 'hryvinskyi_datalayer_server-link',
                ],
            ]],
            $this->redirects
        );
    }

    /**
     * At the default scope every website is retried; the count is told in the singular or as none
     *
     * @return void
     */
    public function testDefaultScopeRetriesEveryWebsite(): void
    {
        $this->controller(null, static fn (): int => 1)->execute();
        $this->controller('0', static fn (): int => 0)->execute();

        self::assertSame([null, null], $this->retried);
        self::assertSame([
            ['success', '1 failed event is queued again.'],
            ['success', 'No failed event was queued again: only events less than 72 hours old can still be sent.'],
        ], $this->messages);
        self::assertSame(
            ['section' => 'hryvinskyi_datalayer', '_fragment' => 'hryvinskyi_datalayer_server-link'],
            $this->redirects[0][1]
        );
    }

    /**
     * A failure is logged with its reason; the page shows a sentence of its own, never the exception's text
     *
     * @return void
     */
    public function testFailureIsLoggedNotShown(): void
    {
        $this->controller('3', static function (): int {
            throw new \RuntimeException('SQLSTATE[HY000]: lock wait timeout');
        })->execute();

        self::assertSame(
            [['error', 'The failed events could not be queued again. The log has the details.']],
            $this->messages
        );
        self::assertStringContainsString('website 3', $this->logger->dump());
        self::assertStringContainsString('RuntimeException: SQLSTATE[HY000]: lock wait timeout', $this->logger->dump());
        self::assertSame(
            ['section' => 'hryvinskyi_datalayer', 'website' => 3, '_fragment' => 'hryvinskyi_datalayer_server-link'],
            $this->redirects[0][1]
        );
    }

    /**
     * A scope that is not a website retries nothing
     *
     * @return void
     */
    public function testUnknownScopeRetriesNothing(): void
    {
        $this->controller('base', static fn (): int => 5)->execute();

        self::assertSame([], $this->retried);
        self::assertSame('error', $this->messages[0][0]);
        self::assertSame(
            ['section' => 'hryvinskyi_datalayer', '_fragment' => 'hryvinskyi_datalayer_server-link'],
            $this->redirects[0][1]
        );
    }

    /**
     * Build the controller over a request carrying the website parameter
     *
     * @param string|null $website
     * @param \Closure(): int $retry What the retry service does
     * @return RetryFailed
     */
    private function controller(?string $website, \Closure $retry): RetryFailed
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name): ?string => $name === 'website' ? $website : null
        );
        $messages = $this->createMock(ManagerInterface::class);
        $messages->method('addSuccessMessage')->willReturnCallback(function (string $text) use ($messages) {
            $this->messages[] = ['success', $text];

            return $messages;
        });
        $messages->method('addErrorMessage')->willReturnCallback(function (string $text) use ($messages) {
            $this->messages[] = ['error', $text];

            return $messages;
        });
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function (string $path, array $params = []) use ($redirect) {
            $this->redirects[] = [$path, $params];

            return $redirect;
        });
        $redirects = $this->createMock(RedirectFactory::class);
        $redirects->method('create')->willReturn($redirect);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getMessageManager')->willReturn($messages);
        $context->method('getResultRedirectFactory')->willReturn($redirects);

        $service = $this->createMock(FailedEventRetryInterface::class);
        $service->method('retry')->willReturnCallback(function (?int $websiteId) use ($retry): int {
            $this->retried[] = $websiteId;

            return $retry();
        });

        return new RetryFailed(
            $context,
            new RetryFailedEvents($service, new DeliveryActionNotices(), $this->logger),
            new WebsiteParameter(),
            new DeliveryActionNotices(),
            new SettingsGroupLink()
        );
    }
}
