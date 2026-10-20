<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Controller\Adminhtml\Setup;

use Hryvinskyi\DataLayerServerSide\Test\Integration\ReadsTables;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ResolvesServices;
use Hryvinskyi\DataLayerServerSide\Test\Integration\SeedsOutbox;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The setup wizard's "Retry failed", called the way its browser code calls it: the failed events of the scope's
 * website are queued again, and the answer says how many with where delivery now stands
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class RetryFailedTest extends AbstractBackendController
{
    use ResolvesServices;
    use ReadsTables;
    use SeedsOutbox;

    private const OTHER_WEBSITE = 7;

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_DataLayerServerSideAdminUi::delivery';

    /**
     * @var string
     */
    protected $uri = 'backend/hryvinskyi_datalayer_server/setup/retryFailed';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * Send every request as the browser does: as AJAX, with the form key in the query string and a JSON body
     *
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $request = $this->httpRequest();
        $request->setQueryValue('isAjax', 'true');
        $request->setQueryValue('form_key', $this->service(FormKey::class)->getFormKey());
        $request->setContent('{}');
    }

    /**
     * A store view queues its website's failed events again, not another website's; the answer carries the count and
     * the website's status after it
     *
     * @return void
     */
    public function testRetriesTheWebsiteOfTheScope(): void
    {
        $websiteId = $this->baseWebsiteId();
        $first = $this->seed('it_wizard_retry_one', $this->failedColumns($websiteId));
        $second = $this->seed('it_wizard_retry_two', $this->failedColumns($websiteId));
        $elsewhere = $this->seed('it_wizard_retry_elsewhere', $this->failedColumns(self::OTHER_WEBSITE));
        $storeId = $this->storeViewId();

        $answer = $this->post(['scope' => 'store:' . $storeId], 200);

        self::assertTrue($answer['done'] ?? null);
        self::assertSame('2 failed events are queued again.', $answer['message'] ?? null);
        $status = $answer['status'] ?? null;
        self::assertIsArray($status);
        self::assertSame(0, $status['failed'] ?? null);
        self::assertGreaterThanOrEqual(2, $status['pending'] ?? null);
        self::assertSame('', $status['failedLine'] ?? null);
        self::assertSame('pending', $this->outboxRow($first)['status'] ?? null);
        self::assertSame('pending', $this->outboxRow($second)['status'] ?? null);
        self::assertSame('failed', $this->outboxRow($elsewhere)['status'] ?? null);
    }

    /**
     * A scope the store does not have answers 400 and queues nothing
     *
     * @return void
     */
    public function testUnknownScopeAnswers400(): void
    {
        $failed = $this->seed('it_wizard_retry_kept', $this->failedColumns($this->baseWebsiteId()));

        self::assertSame(
            ['error' => '"store:99999" is not a scope of this store.'],
            $this->post(['scope' => 'store:99999'], 400)
        );
        self::assertSame('failed', $this->outboxRow($failed)['status'] ?? null);
    }

    /**
     * Post a JSON body and decode the answer
     *
     * @param array<string, mixed> $body
     * @param int $status The status the answer must have
     * @return array<mixed>
     */
    private function post(array $body, int $status): array
    {
        $request = $this->httpRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setContent((string) json_encode($body));
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame($status, $response->getHttpResponseCode(), (string) $response->getBody());
        $answer = json_decode((string) $response->getBody(), true);
        self::assertIsArray($answer);

        return $answer;
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
     * Get the default store view's id
     *
     * @return int
     */
    private function storeViewId(): int
    {
        $store = $this->service(StoreManagerInterface::class)->getDefaultStoreView();
        self::assertNotNull($store);

        return (int) $store->getId();
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

    /**
     * Get the request
     *
     * @return HttpRequest
     */
    private function httpRequest(): HttpRequest
    {
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);

        return $request;
    }
}
