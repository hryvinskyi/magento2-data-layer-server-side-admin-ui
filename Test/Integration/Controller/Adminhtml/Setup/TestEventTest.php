<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Controller\Adminhtml\Setup;

use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;
use Hryvinskyi\DataLayerServerSide\Model\Data\TransportResult;
use Hryvinskyi\DataLayerServerSide\Model\Transport\TransportPool;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ResolvesServices;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Delivery\TestEventSender;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\DeniesDeliveryActions;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Fake\ConfigReadingTransport;
use Magento\Backend\Model\Auth;
use Magento\Config\Model\ConfigFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The setup wizard's "Send test event", called the way its browser code calls it: the unsaved settings of a scope
 * reach the transport as the configuration reader answers them while the event is sent, nothing is saved, a secret
 * the person did not retype is the stored one, and an administrator without the delivery actions' permission is
 * refused
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class TestEventTest extends AbstractBackendController
{
    use ResolvesServices;
    use DeniesDeliveryActions;

    private const STORED_SECRET = 'it-Stored-secret/7';
    private const SERVER_PATHS = 'hryvinskyi_datalayer_server/%';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_DataLayerServerSideAdminUi::delivery';

    /**
     * @var string
     */
    protected $uri = 'backend/hryvinskyi_datalayer_server/setup/testEvent';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * @var ConfigReadingTransport
     */
    private ConfigReadingTransport $transport;

    /**
     * Send every request as the browser does, through a transport that records what it reads
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

        $objectManager = $this->testObjectManager();
        $this->transport = new ConfigReadingTransport(
            $this->service(ConfigInterface::class),
            new TransportResult(200, true, false)
        );
        $pool = $objectManager->create(TransportPool::class, ['transports' => ['ga4' => $this->transport]]);
        self::assertInstanceOf(TransportPool::class, $pool);
        $objectManager->removeSharedInstance(TestEventSender::class);
        $objectManager->addSharedInstance($pool, TransportPool::class);
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
     * Unsaved settings are what the transport reads, the measurement ID as it would be saved; afterwards the stored
     * settings read again and nothing was written
     *
     * @return void
     */
    public function testDraftedSettingsReachTheTransport(): void
    {
        $websiteId = $this->baseWebsiteId();
        $rows = $this->serverRows();

        $answer = $this->post([
            'scope' => 'website:' . $websiteId,
            'settings' => [
                'ss_enabled' => true,
                'transport' => 'ga4',
                'mp_id' => 'g-draft42',
                'api_secret' => 'it-Draft-secret/1',
            ],
        ], 200);

        self::assertSame(['valid' => true, 'messages' => [], 'error' => ''], $answer);
        self::assertSame([[
            'websiteId' => $websiteId,
            'debug' => true,
            'measurementId' => 'G-DRAFT42',
            'secret' => 'it-Draft-secret/1',
        ]], $this->transport->sent);
        self::assertNull($this->service(ConfigInterface::class)->getMeasurementId($websiteId));
        self::assertSame($rows, $this->serverRows());
    }

    /**
     * A store view tests its website's settings, with the stored secret when the person did not type a new one
     *
     * @return void
     */
    public function testStoredSecretIsUsedWhenNotRetyped(): void
    {
        $websiteId = $this->baseWebsiteId();
        $this->saveSettings($websiteId);
        $storeId = $this->storeViewId();

        $answer = $this->post([
            'scope' => 'store:' . $storeId,
            'settings' => ['ss_enabled' => true, 'transport' => 'ga4', 'mp_id' => 'G-OTHER7'],
        ], 200);

        self::assertTrue($answer['valid'] ?? null);
        self::assertSame(self::STORED_SECRET, $this->transport->sent[0]['secret'] ?? null);
        self::assertSame('G-OTHER7', $this->transport->sent[0]['measurementId'] ?? null);
    }

    /**
     * A drafted transport without a validation endpoint is not tested
     *
     * @return void
     */
    public function testDraftedServerContainerIsNotTested(): void
    {
        $answer = $this->post([
            'scope' => 'website:' . $this->baseWebsiteId(),
            'settings' => ['ss_enabled' => true, 'transport' => 'sgtm', 'mp_id' => 'G-X1', 'api_secret' => 'x'],
        ], 200);

        self::assertSame(
            'Testing is available for Google Analytics (Measurement Protocol) only.',
            $answer['error'] ?? null
        );
        self::assertSame([], $this->transport->sent);
    }

    /**
     * A scope the store does not have, or a setting of another step, answers 400 and sends nothing
     *
     * @return void
     */
    public function testUnreadableRequestAnswers400(): void
    {
        self::assertSame(
            ['error' => '"website:99999" is not a scope of this store.'],
            $this->post(['scope' => 'website:99999', 'settings' => []], 400)
        );
        self::assertSame(
            ['error' => 'The setting "enabled" is not one a test event reads.'],
            $this->post(['scope' => 'default', 'settings' => ['enabled' => true]], 400)
        );
        self::assertSame([], $this->transport->sent);
    }

    /**
     * An administrator whose role lacks the delivery actions' permission is refused, and nothing is sent
     *
     * @return void
     */
    public function testRefusedWithoutTheDeliveryPermission(): void
    {
        $this->denyDeliveryActions($this->service(Auth::class));

        $request = $this->httpRequest();
        $request->setMethod(HttpRequest::METHOD_POST);
        $request->setContent((string) json_encode(['scope' => 'default', 'settings' => ['ss_enabled' => true]]));
        $this->dispatch($this->uri);

        self::assertSame(403, $this->response()->getHttpResponseCode());
        self::assertSame([], $this->transport->sent);
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
        $response = $this->response();
        self::assertSame($status, $response->getHttpResponseCode(), (string) $response->getBody());
        $answer = json_decode((string) $response->getBody(), true);
        self::assertIsArray($answer);

        return $answer;
    }

    /**
     * Save working Google Analytics settings for a website, the way the settings form posts them
     *
     * @param int $websiteId
     * @return void
     */
    private function saveSettings(int $websiteId): void
    {
        $this->service(ConfigFactory::class)->create(['data' => [
            'section' => 'hryvinskyi_datalayer',
            'website' => (string) $websiteId,
            'store' => null,
            'groups' => ['server' => ['groups' => [
                'delivery' => ['fields' => [
                    'enabled' => ['value' => '0'],
                    'transport' => ['value' => 'ga4'],
                    'measurement_id' => ['value' => 'G-SAVED1'],
                ]],
                'ga4' => ['fields' => ['api_secret' => ['value' => self::STORED_SECRET]]],
            ]]],
        ]])->save();
        $this->service(ReinitableConfigInterface::class)->reinit();
    }

    /**
     * Read the stored server-side settings
     *
     * @return array<mixed>
     */
    private function serverRows(): array
    {
        $resource = $this->service(ResourceConnection::class);
        $connection = $resource->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from($resource->getTableName('core_config_data'), ['scope', 'scope_id', 'path', 'value'])
                ->where('path LIKE ?', self::SERVER_PATHS)
                ->order(['path', 'scope', 'scope_id'])
        );
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

    /**
     * Get the response
     *
     * @return HttpResponse
     */
    private function response(): HttpResponse
    {
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);

        return $response;
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
