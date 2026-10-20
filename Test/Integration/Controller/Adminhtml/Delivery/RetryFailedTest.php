<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Controller\Adminhtml\Delivery;

use Hryvinskyi\DataLayerServerSide\Test\Integration\ReadsTables;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ResolvesServices;
use Hryvinskyi\DataLayerServerSide\Test\Integration\SeedsOutbox;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\DeniesDeliveryActions;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * "Retry failed" posted from the settings queues the failed events of the scope shown again, through the real
 * routing, access rule, form key check and outbox table
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class RetryFailedTest extends AbstractBackendController
{
    use ResolvesServices;
    use ReadsTables;
    use SeedsOutbox;
    use DeniesDeliveryActions;

    private const OTHER_WEBSITE = 7;

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_DataLayerServerSideAdminUi::delivery';

    /**
     * @var string
     */
    protected $uri = 'backend/hryvinskyi_datalayer_server/delivery/retryFailed';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * At one website's scope only that website's failed events are pending again, due now, attempts reset; the
     * administrator returns to that website's settings with the count
     *
     * @return void
     */
    public function testRetriesOnlyTheWebsiteShown(): void
    {
        $websiteId = $this->baseWebsiteId();
        $first = $this->seed('it_retry_one', $this->failedColumns($websiteId));
        $second = $this->seed('it_retry_two', $this->failedColumns($websiteId));
        $elsewhere = $this->seed('it_retry_elsewhere', $this->failedColumns(self::OTHER_WEBSITE));
        $sent = $this->seed('it_retry_sent', ['website_id' => $websiteId, 'status' => 'sent', 'attempts' => 1]);
        $before = time();

        $this->post(['website' => (string) $websiteId]);

        foreach ([$first, $second] as $eventId) {
            $row = $this->outboxRow($eventId);
            self::assertSame('pending', $row['status'] ?? null);
            self::assertSame('0', $row['attempts'] ?? null);
            self::assertNull($row['claim_token'] ?? null);
            self::assertLessThanOrEqual(1, abs($this->unixTime($row['next_attempt_at'] ?? null) - $before));
        }
        self::assertSame('failed', $this->outboxRow($elsewhere)['status'] ?? null);
        self::assertSame('8', $this->outboxRow($elsewhere)['attempts'] ?? null);
        self::assertSame('sent', $this->outboxRow($sent)['status'] ?? null);

        $this->assertRedirect(self::stringContains(
            'admin/system_config/edit/section/hryvinskyi_datalayer/website/' . $websiteId . '/'
        ));
        $this->assertRedirect(self::stringEndsWith('#hryvinskyi_datalayer_server-link'));
        $this->assertSessionMessages(
            self::equalTo(['2 failed events are queued again.']),
            MessageInterface::TYPE_SUCCESS
        );
    }

    /**
     * An administrator whose role has the settings' rule but not the delivery actions' is refused, and no event
     * changes
     *
     * @return void
     */
    public function testRefusedWithoutTheDeliveryRule(): void
    {
        $websiteId = $this->baseWebsiteId();
        $failed = $this->seed('it_retry_refused', $this->failedColumns($websiteId));
        $this->denyDeliveryActions($this->_auth);

        $this->post(['website' => (string) $websiteId]);

        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(403, $response->getHttpResponseCode());
        $row = $this->outboxRow($failed);
        self::assertSame('failed', $row['status'] ?? null);
        self::assertSame('8', $row['attempts'] ?? null);
    }

    /**
     * Post to the retry endpoint
     *
     * @param array<string, string> $fields
     * @return void
     */
    private function post(array $fields): void
    {
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setPostValue($fields);
        $this->dispatch($this->uri);
    }

    /**
     * Columns of an event given up on
     *
     * @param int $websiteId
     * @return array<string, int|string|null>
     */
    private function failedColumns(int $websiteId): array
    {
        return [
            'website_id' => $websiteId,
            'status' => 'failed',
            'attempts' => 8,
            'next_attempt_at' => $this->utc(-3600),
            'last_error' => 'HTTP 503',
        ];
    }

    /**
     * Get the main website's id
     *
     * @return int
     */
    private function baseWebsiteId(): int
    {
        return (int) $this->service(StoreManagerInterface::class)->getWebsite('base')->getId();
    }
}
