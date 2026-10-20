<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Unit\Fake;

use Hryvinskyi\DataLayerAdminUi\Model\Setup\Kind\ChoiceKind;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\Kind\FlagKind;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\Kind\NumberKind;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\Kind\SecretKind;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\Kind\TextKind;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\SettingDescriptors;
use Hryvinskyi\DataLayerAdminUi\Model\Setup\SettingKinds;
use Hryvinskyi\DataLayerAdminUi\Test\Unit\Model\Setup\PresentationStub;
use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;

/**
 * The wizard's setting pool and kinds as this module registers its settings, beside one setting of another step
 *
 * The transport offers `ga4` and `sgtm`, the region `global` and `eu`.
 */
class Settings
{
    /**
     * Build the setting kinds the wizard registers
     *
     * @return SettingKinds
     */
    public static function kinds(): SettingKinds
    {
        return new SettingKinds([
            'flag' => new FlagKind(),
            'choice' => new ChoiceKind(new PresentationStub([
                'hryvinskyi_datalayer_server/general/transport' => ['ga4', 'sgtm'],
                'hryvinskyi_datalayer_server/ga4/region' => ['global', 'eu'],
            ])),
            'text' => new TextKind(),
            'number' => new NumberKind(),
            'secret' => new SecretKind(),
        ]);
    }

    /**
     * Build the setting pool
     *
     * @return SettingDescriptors
     */
    public static function descriptors(): SettingDescriptors
    {
        $server = static fn (string $path, string $kind, array $extra = []): array
            => ['path' => 'hryvinskyi_datalayer_server/' . $path, 'kind' => $kind, 'step' => 'server'] + $extra;

        return new SettingDescriptors(new DescriptorFactory(), self::kinds(), [
            'enabled' => ['path' => 'hryvinskyi_datalayer/general/enabled', 'kind' => 'flag', 'step' => 'connect'],
            'ss_enabled' => $server('general/enabled', 'flag'),
            'transport' => $server('general/transport', 'choice'),
            'mp_id' => $server('ga4/measurement_id', 'text', [
                'pattern' => ConfigInterface::MEASUREMENT_ID_PATTERN,
                'caseInsensitive' => true,
            ]),
            'api_secret' => $server('ga4/api_secret', 'secret'),
            'region' => $server('ga4/region', 'choice'),
            'endpoint' => $server('sgtm/endpoint', 'text'),
            'sgtm_secret' => $server('sgtm/api_secret', 'secret'),
            'visitor_days' => $server('identity/retention_days', 'number', ['min' => 30, 'max' => 1000]),
        ]);
    }
}
