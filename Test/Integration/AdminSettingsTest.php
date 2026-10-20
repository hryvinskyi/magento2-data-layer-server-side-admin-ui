<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration;

use Hryvinskyi\DataLayerServerSide\Test\Integration\ReadsTables;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ResolvesServices;
use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;
use Magento\Config\Model\Config\TypePool;
use Magento\Config\Model\ConfigFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Settings saved the way the admin form posts them are validated, stored normalised, and read back typed; the API
 * secrets are stored encrypted and read back decrypted
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class AdminSettingsTest extends TestCase
{
    use ResolvesServices;
    use ReadsTables;

    private const SECTION = 'hryvinskyi_datalayer';
    private const STORED = 'hryvinskyi_datalayer_server/';

    /**
     * Valid values are stored normalised at website scope and read back through the settings, secrets decrypted
     *
     * @return void
     */
    public function testValidSettingsAreStoredAndReadBack(): void
    {
        $this->save([
            'delivery' => ['fields' => [
                'enabled' => ['value' => '1'],
                'transport' => ['value' => 'sgtm'],
                'measurement_id' => ['value' => ' g-abc123 '],
                'max_attempts' => ['value' => '3'],
            ]],
            'ga4' => ['fields' => ['api_secret' => ['value' => 'ga4-secret-value']]],
            'sgtm' => ['fields' => [
                'endpoint' => ['value' => 'https://sgtm.example.com/'],
                'api_secret' => ['value' => 'container-secret-value'],
            ]],
            'identity' => ['fields' => ['retention_days' => ['value' => ' 90 ']]],
        ]);
        $this->service(ReinitableConfigInterface::class)->reinit();

        $websiteId = $this->websiteId();
        $config = $this->service(ConfigInterface::class);
        self::assertTrue($config->isEnabled($websiteId));
        self::assertSame('sgtm', $config->getTransport($websiteId));
        self::assertSame('G-ABC123', $config->getMeasurementId($websiteId));
        self::assertSame('https://sgtm.example.com', $config->getServerContainerEndpoint($websiteId));
        self::assertSame(90, $config->getRetentionDays($websiteId));
        self::assertSame(3, $config->getMaxAttempts($websiteId));
        self::assertSame('ga4-secret-value', $config->getGa4ApiSecret($websiteId));
        self::assertSame('container-secret-value', $config->getServerContainerApiSecret($websiteId));

        $stored = $this->rows('core_config_data', 'path = ?', self::STORED . 'ga4/api_secret');
        self::assertCount(1, $stored);
        self::assertNotSame('ga4-secret-value', $stored[0]['value'] ?? null);
        self::assertStringNotContainsString('ga4-secret-value', $stored[0]['value'] ?? '');
    }

    /**
     * A value outside what a setting allows is refused
     *
     * @dataProvider invalidValues
     * @param string $group
     * @param string $field
     * @param string $value
     * @return void
     */
    #[DataProvider('invalidValues')]
    public function testInvalidValueIsRefused(string $group, string $field, string $value): void
    {
        $this->expectException(LocalizedException::class);

        $this->save([$group => ['fields' => [$field => ['value' => $value]]]]);
    }

    /**
     * Values each setting refuses
     *
     * @return array<string, array{string, string, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'universal analytics id' => ['delivery', 'measurement_id', 'UA-12345-1'],
            'plain http container' => ['sgtm', 'endpoint', 'http://sgtm.example.com'],
            'container with a query string' => ['sgtm', 'endpoint', 'https://sgtm.example.com/?api_secret=x'],
            'container with a path' => ['sgtm', 'endpoint', 'https://sgtm.example.com/metrics'],
            'container on localhost' => ['sgtm', 'endpoint', 'https://localhost:8443'],
            'container on a loopback address' => ['sgtm', 'endpoint', 'https://127.0.0.1'],
            'container on a private address' => ['sgtm', 'endpoint', 'https://10.0.0.8'],
            'container on the metadata service' => ['sgtm', 'endpoint', 'https://169.254.169.254'],
            'container on a decimal address' => ['sgtm', 'endpoint', 'https://2130706433'],
            'retention too short' => ['identity', 'retention_days', '7'],
            'retention not a number' => ['identity', 'retention_days', 'forever'],
            'too many attempts' => ['delivery', 'max_attempts', '50'],
        ];
    }

    /**
     * Both API secrets are sensitive settings, so the shared configuration dump leaves them out
     *
     * @return void
     */
    public function testApiSecretsAreSensitive(): void
    {
        $types = $this->service(TypePool::class);

        self::assertTrue($types->isPresent(self::STORED . 'ga4/api_secret', TypePool::TYPE_SENSITIVE));
        self::assertTrue($types->isPresent(self::STORED . 'sgtm/api_secret', TypePool::TYPE_SENSITIVE));
        self::assertFalse($types->isPresent(self::STORED . 'ga4/measurement_id', TypePool::TYPE_SENSITIVE));
    }

    /**
     * Save groups of the server-side group at the default website's scope, the way the admin form posts them
     *
     * @param array<string, mixed> $groups The server-side group's own groups
     * @return void
     */
    private function save(array $groups): void
    {
        $this->service(ConfigFactory::class)->create(
            ['data' => [
                'section' => self::SECTION,
                'website' => (string) $this->websiteId(),
                'store' => null,
                'groups' => ['server' => ['groups' => $groups]],
            ]]
        )->save();
    }

    /**
     * Get the default website's id
     *
     * @return int
     */
    private function websiteId(): int
    {
        return (int) $this->service(StoreManagerInterface::class)->getWebsite('base')->getId();
    }
}
