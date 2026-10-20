<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Controller\Adminhtml\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\Http\JsonBodyInterface;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\Http\JsonAnswer;
use Hryvinskyi\DataLayerAdminUi\Test\Unit\Model\Setup\RecordingLogger;
use Hryvinskyi\DataLayerServerSide\Api\Data\DeliveryReportInterface;
use Hryvinskyi\DataLayerServerSide\Api\DeliveryStatusInterface;
use Hryvinskyi\DataLayerServerSide\Model\Data\DeliveryReport;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\DeliveryStatusViewInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\RetryFailedEventsInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Controller\Adminhtml\Setup\RetryFailed;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup\ScopeWebsite;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\FixedScopeTree;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\ScopeIdFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\TestCase;

/**
 * The wizard's retry endpoint queues the failed events of the scope's website (every website at the default scope)
 * and answers with the outcome and the new status; an unknown scope answers 400 and queues nothing; a failure of the
 * server answers 500 with a generic sentence and is logged
 */
class RetryFailedTest extends TestCase
{
    /**
     * What the endpoint logged
     *
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * Start every test with nothing logged
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    /**
     * @var list<mixed>
     */
    private array $answers = [];

    /**
     * @var list<int>
     */
    private array $codes = [];

    /**
     * @var list<int|null>
     */
    private array $retried = [];

    /**
     * A store view retries its website; the default scope every website
     *
     * @return void
     */
    public function testRetriesTheScopesWebsite(): void
    {
        $this->controller(['scope' => 'store:4'])->execute();
        $this->controller(['scope' => 'default'])->execute();

        self::assertSame([3, null], $this->retried);
        self::assertSame([
            ['done' => true, 'message' => 'queued', 'status' => $this->statusOf(3)],
            ['done' => true, 'message' => 'queued', 'status' => $this->statusOf(null)],
        ], $this->answers);
    }

    /**
     * An unknown scope answers 400 with the reason
     *
     * @return void
     */
    public function testUnknownScopeAnswers400(): void
    {
        $this->controller(['scope' => 'website:9'])->execute();

        self::assertSame([], $this->retried);
        self::assertSame([400], $this->codes);
        self::assertSame([['error' => '"website:9" is not a scope of this store.']], $this->answers);
        self::assertSame([], $this->logger->records);
    }

    /**
     * A retry that fails for any other reason answers 500 with a generic sentence, never its own message, and is
     * logged with the endpoint
     *
     * @return void
     */
    public function testFailureAnswers500AndIsLogged(): void
    {
        $this->controller(['scope' => 'default'], new \RuntimeException('SQLSTATE[40001]: deadlock'))->execute();

        self::assertSame([500], $this->codes);
        self::assertSame(
            [['error' => 'The request could not be completed. Details are in the log.']],
            $this->answers
        );
        self::assertSame('retryFailed', $this->logger->records[0]['context']['endpoint'] ?? null);
    }

    /**
     * The status the fake view describes for a website
     *
     * @param int|null $websiteId
     * @return array{pending: int, sent: int, failed: int, progress: string, failedLine: string}
     */
    private function statusOf(?int $websiteId): array
    {
        return [
            'pending' => 0,
            'sent' => 0,
            'failed' => 0,
            'progress' => 'of ' . ($websiteId ?? 'all'),
            'failedLine' => '',
        ];
    }

    /**
     * Build the controller over a JSON body, and a retry that fails as given
     *
     * @param array<mixed> $body
     * @param \Throwable|null $failure
     * @return RetryFailed
     */
    private function controller(array $body, ?\Throwable $failure = null): RetryFailed
    {
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($this->createStub(RequestInterface::class));
        $jsonBody = $this->createStub(JsonBodyInterface::class);
        $jsonBody->method('read')->willReturn($body);
        $retry = $this->createStub(RetryFailedEventsInterface::class);
        $retry->method('retry')->willReturnCallback(function (?int $websiteId) use ($failure): array {
            if ($failure !== null) {
                throw $failure;
            }
            $this->retried[] = $websiteId;

            return ['done' => true, 'message' => 'queued'];
        });
        $deliveryStatus = $this->createStub(DeliveryStatusInterface::class);
        $deliveryStatus->method('forWebsite')
            ->willReturnCallback(static fn (?int $websiteId): DeliveryReport => new DeliveryReport($websiteId));
        $view = $this->createStub(DeliveryStatusViewInterface::class);
        $view->method('describe')->willReturnCallback(
            fn (DeliveryReportInterface $report): array => $this->statusOf($report->getWebsiteId())
        );
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function (mixed $data) use ($json): Json {
            $this->answers[] = $data;

            return $json;
        });
        $json->method('setHttpResponseCode')->willReturnCallback(function (int $code) use ($json): Json {
            $this->codes[] = $code;

            return $json;
        });
        $factory = $this->createStub(JsonFactory::class);
        $factory->method('create')->willReturn($json);

        return new RetryFailed(
            $context,
            $jsonBody,
            new ScopeWebsite(new FixedScopeTree(), new ScopeIdFactory()),
            $retry,
            $deliveryStatus,
            $view,
            new JsonAnswer($factory, $this->logger)
        );
    }
}
