<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Model\Setup;

use Hryvinskyi\DataLayerAdminUi\Api\Setup\InvalidSetupRequestException;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\SettingDescriptorsInterface;
use Hryvinskyi\DataLayerAdminUi\Api\Setup\SettingKindsInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\ServerStepDataInterface;
use Hryvinskyi\DataLayerServerSideAdminUi\Api\Setup\TestEventDraftInterface;

/**
 * Drafts each server-side setting under its config path in the form its kind stores
 *
 * Only settings of the server-side step are accepted: a test event reads nothing else, and drafting another step's
 * setting would test something the person did not ask for.
 */
class TestEventDraft implements TestEventDraftInterface
{
    /**
     * @param SettingDescriptorsInterface $descriptors
     * @param SettingKindsInterface $kinds
     */
    public function __construct(
        private readonly SettingDescriptorsInterface $descriptors,
        private readonly SettingKindsInterface $kinds
    ) {
    }

    /**
     * @inheritDoc
     */
    public function toDraft(array $settings): array
    {
        $draft = [];
        foreach ($settings as $key => $value) {
            $descriptor = $this->descriptors->get((string) $key);
            if ($descriptor->getStep() !== ServerStepDataInterface::STEP) {
                throw new InvalidSetupRequestException(
                    __('The setting "%1" is not one a test event reads.', (string) $key)
                );
            }
            $draft[$descriptor->getPath()] = $this->kinds->get($descriptor->getKind())->toStored($descriptor, $value);
        }

        return $draft;
    }
}
