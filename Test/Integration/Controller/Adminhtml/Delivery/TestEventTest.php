<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Controller\Adminhtml\Delivery;

use Hryvinskyi\DataLayerServerSide\Api\Data\TransportResultInterface;
use Hryvinskyi\DataLayerServerSide\Model\Data\TransportResult;
use Hryvinskyi\DataLayerServerSide\Model\Transport\TransportPool;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ResolvesServices;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\TestEventSender;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake\RecordingTransport;
use Magento\Config\Model\ConfigFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * "Send test event" posted from the settings sends one event through the container's transport for the saved
 * settings, in debug mode, and answers with JSON; nothing reaches the outbox and the secret is never in the answer
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class TestEventTest extends AbstractBackendController
{
    use ResolvesServices;

    private const SECRET = 'it-Ga4-secret/Value+9';
    private const SECTION = 'hryvinskyi_datalayer';
    private const OUTBOX = 'hryvinskyi_datalayer_server_event';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_DataLayerServerSideAdminUi::delivery';

    /**
     * @var string
     */
    protected $uri = 'backend/hryvinskyi_datalayer_server/delivery/testEvent';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * @var RecordingTransport
     */
    private RecordingTransport $transport;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->useTransport(new TransportResult(200, true, false));
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $objectManager = $this->testObjectManager();
        $objectManager->removeSharedInstance(TransportPool::class);
        $objectManager->removeSharedInstance(TestEventSender::class);
        parent::tearDown();
    }

    /**
     * A reply without findings answers valid; the one request went to the validation endpoint for the website and
     * left no outbox row
     *
     * @return void
     */
    public function testValidAnswerAndNoOutboxRow(): void
    {
        $websiteId = $this->baseWebsiteId();
        $this->saveGa4Settings($websiteId);
        $outboxRows = $this->outboxCount();

        $answer = $this->post($websiteId);

        self::assertSame(['valid' => true, 'messages' => [], 'error' => ''], $answer);
        self::assertCount(1, $this->transport->sent);
        self::assertTrue($this->transport->sent[0]['debug']);
        self::assertSame($websiteId, $this->transport->sent[0]['websiteId']);
        self::assertStringContainsString('"name":"hryvinskyi_datalayer_test"', $this->transport->sent[0]['body']);
        self::assertSame($outboxRows, $this->outboxCount());
    }

    /**
     * An answer that echoes the request URL, secret included, reaches the page without the secret
     *
     * @return void
     */
    public function testSecretNeverInTheAnswer(): void
    {
        $this->useTransport(new TransportResult(
            0,
            false,
            true,
            'No answer: timeout for https://www.google-analytics.com/debug/mp/collect?api_secret=' . self::SECRET
                . ' (secret ' . self::SECRET . ')'
        ));
        $websiteId = $this->baseWebsiteId();
        $this->saveGa4Settings($websiteId);

        $raw = $this->postRaw($websiteId);

        self::assertStringNotContainsString(self::SECRET, $raw);
        self::assertStringNotContainsString(rawurlencode(self::SECRET), $raw);
        self::assertStringContainsString('No answer: timeout for https:', $raw);
        self::assertCount(1, $this->transport->sent);
    }

    /**
     * A website delivering to a server container is not tested: nothing is sent and the answer says why
     *
     * @return void
     */
    public function testServerContainerIsNotTested(): void
    {
        $websiteId = $this->baseWebsiteId();
        $this->saveGa4Settings($websiteId);
        $this->saveSettings(['delivery' => ['fields' => ['transport' => ['value' => 'sgtm']]]], $websiteId);

        $answer = $this->post($websiteId);

        self::assertSame([
            'valid' => false,
            'messages' => [],
            'error' => 'Testing is available for Google Analytics (Measurement Protocol) only.',
        ], $answer);
        self::assertSame([], $this->transport->sent);
    }

    /**
     * Replace the container's transports with a recording one answering as given
     *
     * @param TransportResultInterface $answer
     * @return void
     */
    private function useTransport(TransportResultInterface $answer): void
    {
        $objectManager = $this->testObjectManager();
        $this->transport = new RecordingTransport($answer);
        $pool = $objectManager->create(TransportPool::class, ['transports' => ['ga4' => $this->transport]]);
        self::assertInstanceOf(TransportPool::class, $pool);
        $objectManager->removeSharedInstance(TestEventSender::class);
        $objectManager->addSharedInstance($pool, TransportPool::class);
    }

    /**
     * Save working Google Analytics settings for a website, the way the settings form posts them
     *
     * @param int $websiteId
     * @return void
     */
    private function saveGa4Settings(int $websiteId): void
    {
        $this->saveSettings([
            'delivery' => ['fields' => [
                'enabled' => ['value' => '1'],
                'transport' => ['value' => 'ga4'],
                'measurement_id' => ['value' => 'G-IT12345'],
            ]],
            'ga4' => ['fields' => ['api_secret' => ['value' => self::SECRET]]],
        ], $websiteId);
    }

    /**
     * Save groups of the server-side settings at a website's scope
     *
     * @param array<string, mixed> $groups The server-side group's own groups
     * @param int $websiteId
     * @return void
     */
    private function saveSettings(array $groups, int $websiteId): void
    {
        $this->service(ConfigFactory::class)->create(['data' => [
            'section' => self::SECTION,
            'website' => (string) $websiteId,
            'store' => null,
            'groups' => ['server' => ['groups' => $groups]],
        ]])->save();
        $this->service(ReinitableConfigInterface::class)->reinit();
    }

    /**
     * Post the test for a website and decode the answer
     *
     * @param int $websiteId
     * @return array<mixed>
     */
    private function post(int $websiteId): array
    {
        $answer = json_decode($this->postRaw($websiteId), true);
        self::assertIsArray($answer);

        return $answer;
    }

    /**
     * Post the test for a website
     *
     * @param int $websiteId
     * @return string The answer's body
     */
    private function postRaw(int $websiteId): string
    {
        $request = $this->getRequest();
        self::assertInstanceOf(HttpRequest::class, $request);
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setPostValue(['website' => (string) $websiteId, 'isAjax' => 'true']);
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());
        return $response->getBody();
    }

    /**
     * Count the outbox rows
     *
     * @return int
     */
    private function outboxCount(): int
    {
        $resource = $this->service(ResourceConnection::class);
        $connection = $resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()->from($resource->getTableName(self::OUTBOX), ['COUNT(*)'])
        );
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
     * Get the test framework's object manager, which can hold a prepared shared instance
     *
     * @return ObjectManager
     */
    private function testObjectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }
}
