<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Model\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;
use Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup\TestEventDraft;
use Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake\Settings;
use PHPUnit\Framework\TestCase;

/**
 * The settings a test event is asked for become config paths with the values their kinds would store; only the
 * server-side step's settings are taken, and a secret is drafted only when one is sent
 */
class TestEventDraftTest extends TestCase
{
    /**
     * Each setting lands under its path in its stored form: a flag as "1", a measurement ID in capitals, a secret in
     * plain text
     *
     * @return void
     */
    public function testDraftsEachSettingInItsStoredForm(): void
    {
        $draft = $this->draft()->toDraft([
            'ss_enabled' => true,
            'transport' => 'ga4',
            'mp_id' => ' g-abc123 ',
            'api_secret' => 'typed-secret',
            'visitor_days' => '90',
        ]);

        self::assertSame([
            'hryvinskyi_datalayer_server/general/enabled' => '1',
            'hryvinskyi_datalayer_server/general/transport' => 'ga4',
            'hryvinskyi_datalayer_server/ga4/measurement_id' => 'G-ABC123',
            'hryvinskyi_datalayer_server/ga4/api_secret' => 'typed-secret',
            'hryvinskyi_datalayer_server/identity/retention_days' => '90',
        ], $draft);
    }

    /**
     * A secret the request leaves out is not drafted, so the stored one is read
     *
     * @return void
     */
    public function testSecretLeftOutIsNotDrafted(): void
    {
        $draft = $this->draft()->toDraft(['ss_enabled' => false, 'mp_id' => '']);

        self::assertArrayNotHasKey('hryvinskyi_datalayer_server/ga4/api_secret', $draft);
        self::assertSame('', $draft['hryvinskyi_datalayer_server/ga4/measurement_id']);
    }

    /**
     * A setting of another step is refused rather than drafted into the test
     *
     * @return void
     */
    public function testSettingOfAnotherStepIsRefused(): void
    {
        $this->expectException(InvalidSetupRequestException::class);
        $this->expectExceptionMessage('The setting "enabled" is not one a test event reads.');

        $this->draft()->toDraft(['enabled' => true]);
    }

    /**
     * An unknown key, a value of the wrong type, and a value the setting would not save (a transport that is not
     * offered, a measurement ID in the wrong format, a retention outside its bounds) are refused
     *
     * @return void
     */
    public function testUnusableValuesAreRefused(): void
    {
        foreach ([
            ['no_such_setting' => 'x'],
            ['ss_enabled' => 'yes'],
            ['api_secret' => '***'],
            ['transport' => 'email'],
            ['mp_id' => 'UA-12345'],
            ['visitor_days' => '5'],
        ] as $settings) {
            try {
                $this->draft()->toDraft($settings);
                self::fail('The settings are refused: ' . (string) json_encode($settings));
            } catch (InvalidSetupRequestException $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }
    }

    /**
     * Build the draft over the module's settings
     *
     * @return TestEventDraft
     */
    private function draft(): TestEventDraft
    {
        return new TestEventDraft(Settings::descriptors(), Settings::kinds());
    }
}
