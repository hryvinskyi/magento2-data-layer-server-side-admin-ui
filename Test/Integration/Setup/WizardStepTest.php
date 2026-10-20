<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\SetupPageConfigInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\SetupSaverInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\SetupStateProviderInterface;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ResolvesServices;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The server-side step joins the setup wizard through the real container: its plugin, import, wording and data
 * reach the page configuration, its settings reach the wizard's state with the label and scopes of this module's
 * configuration fields, and a secret saved through the wizard is stored encrypted and reported only as set
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class WizardStepTest extends AbstractBackendController
{
    use ResolvesServices;

    private const SECRET = 'it-Wizard-secret/42';
    private const SECRET_PATH = 'hryvinskyi_datalayer_server/ga4/api_secret';

    /**
     * The wizard's page: the access tests this class inherits open it with the step joined
     *
     * @var string
     */
    protected $uri = 'backend/hryvinskyi_datalayer/setup/index';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_DataLayerAdminUi::config';

    /**
     * The page configuration names the plugin and its module, carries the step's wording, and gives the step's data
     * with the delivery status of the default scope and of every website
     *
     * @return void
     */
    public function testStepJoinsThePageConfiguration(): void
    {
        $config = $this->decoded($this->service(SetupPageConfigInterface::class)->build(null, null));

        self::assertContains(
            ['id' => 'server', 'module' => '@hryvinskyi/datalayer-setup-server'],
            $this->arrayAt($config, 'plugins')
        );
        $phrases = $this->arrayAt($config, 'phrases');
        self::assertSame('Server-side', $phrases['server.step.title'] ?? null);
        self::assertSame('Enter the API secret.', $phrases['error.api_secret.required'] ?? null);

        $data = $this->arrayAt($this->arrayAt($config, 'extensions'), 'server');
        self::assertIsString($data['testUrl'] ?? null);
        self::assertStringContainsString('hryvinskyi_datalayer_server/setup/testEvent', $data['testUrl']);
        self::assertIsString($data['retryUrl'] ?? null);
        self::assertStringContainsString('hryvinskyi_datalayer_server/setup/retryFailed', $data['retryUrl']);
        self::assertTrue($data['canAct'] ?? null);
        self::assertSame(['ga4'], $data['testableTransports'] ?? null);
        self::assertSame(
            ['ga4' => ['mp_id', 'api_secret', 'region'], 'sgtm' => ['mp_id', 'endpoint', 'sgtm_secret']],
            $data['transportFields'] ?? null
        );

        $status = $data['status'] ?? null;
        self::assertIsArray($status);
        $expected = ['default'];
        foreach ($this->service(StoreManagerInterface::class)->getWebsites() as $website) {
            $expected[] = 'website:' . $website->getId();
        }
        self::assertEqualsCanonicalizing($expected, array_keys($status));
        foreach ($status as $view) {
            self::assertIsArray($view);
            self::assertSame(['pending', 'sent', 'failed', 'progress', 'failedLine'], array_keys($view));
        }
    }

    /**
     * Each setting reports the label of this module's configuration field for its path and can be set per website
     * at most, as that field is shown
     *
     * @return void
     */
    public function testSettingsReportTheirConfigurationFields(): void
    {
        $settings = $this->settings();

        self::assertSame('Measurement ID', $settings['mp_id']['label'] ?? null);
        self::assertSame('website', $settings['mp_id']['reach'] ?? null);
        self::assertSame('server', $settings['mp_id']['step'] ?? null);
        self::assertTrue($settings['mp_id']['caseInsensitive'] ?? null);
        self::assertSame(['source' => '^G-[A-Z0-9]+$', 'flags' => ''], $settings['mp_id']['pattern'] ?? null);
        self::assertSame(30, $settings['visitor_days']['min'] ?? null);
        self::assertSame(1000, $settings['visitor_days']['max'] ?? null);
        self::assertSame(1, $settings['max_attempts']['min'] ?? null);
        self::assertSame(20, $settings['max_attempts']['max'] ?? null);
        self::assertSame('i', $this->arrayAt($settings['endpoint'] ?? [], 'pattern')['flags'] ?? null);

        $transports = $settings['transport']['options'] ?? null;
        self::assertIsArray($transports);
        self::assertSame(['ga4', 'sgtm'], array_column($transports, 'value'));
        foreach (array_keys($settings) as $key) {
            self::assertSame('website', $settings[$key]['reach'] ?? null, $key);
        }
        self::assertCount(13, $settings);
    }

    /**
     * A secret saved through the wizard is stored encrypted, and the wizard's state says only that it is set
     *
     * @return void
     */
    public function testSecretRoundTrip(): void
    {
        $websiteId = (int) $this->service(StoreManagerInterface::class)->getWebsite('base')->getId();
        $scope = 'website:' . $websiteId;

        $results = $this->service(SetupSaverInterface::class)->save([
            ['scope' => $scope, 'key' => 'api_secret', 'op' => 'set', 'value' => self::SECRET],
        ]);
        $this->service(ReinitableConfigInterface::class)->reinit();

        self::assertSame([['scope' => $scope, 'key' => 'api_secret', 'status' => 'saved']], $results);
        $stored = $this->storedSecret($websiteId);
        self::assertNotSame(self::SECRET, $stored);
        self::assertSame(self::SECRET, $this->service(EncryptorInterface::class)->decrypt($stored));

        $state = $this->service(SetupStateProviderInterface::class)->getState();
        $settings = $this->settings();
        self::assertSame(['set' => true], $this->arrayAt($settings['api_secret'] ?? [], 'values')[$scope] ?? null);
        self::assertSame(['set' => false], $settings['api_secret']['builtIn'] ?? null);
        self::assertStringNotContainsString(self::SECRET, (string) json_encode($state));
    }

    /**
     * The state's server-side settings, keyed by setting key
     *
     * @return array<string, array<string, mixed>>
     */
    private function settings(): array
    {
        $settings = [];
        foreach ($this->service(SetupStateProviderInterface::class)->getState()['settings'] as $setting) {
            if ($setting['step'] === 'server') {
                $decoded = $this->decoded($setting);
                $settings[$setting['key']] = array_filter(
                    $decoded,
                    static fn (int|string $name): bool => is_string($name),
                    ARRAY_FILTER_USE_KEY
                );
            }
        }

        return $settings;
    }

    /**
     * Read the stored secret of a website
     *
     * @param int $websiteId
     * @return string
     */
    private function storedSecret(int $websiteId): string
    {
        $resource = $this->service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('core_config_data'), ['value'])
                ->where('path = ?', self::SECRET_PATH)
                ->where('scope = ?', 'websites')
                ->where('scope_id = ?', $websiteId)
        );
        self::assertNotSame('', $value);

        return $value;
    }

    /**
     * Turn a value into the arrays it encodes to as JSON, as the browser reads it
     *
     * @param mixed $value
     * @return array<mixed>
     */
    private function decoded(mixed $value): array
    {
        $decoded = json_decode((string) json_encode($value), true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * Get an array held under a key
     *
     * @param array<mixed> $array
     * @param string $key
     * @return array<mixed>
     */
    private function arrayAt(array $array, string $key): array
    {
        $value = $array[$key] ?? null;
        self::assertIsArray($value, $key);

        return $value;
    }
}
