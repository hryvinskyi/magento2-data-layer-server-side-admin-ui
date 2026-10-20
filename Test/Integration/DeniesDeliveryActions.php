<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideAdminUi\Test\Integration;

use Magento\Backend\Model\Auth;
use Magento\Framework\Acl\Builder;
use Magento\User\Model\User;

/**
 * Takes the delivery actions' access rule away from the signed-in administrator's role, keeping the settings' rule,
 * the way a role granted only the settings is set up
 */
trait DeniesDeliveryActions
{
    /**
     * Deny the delivery actions to the signed-in administrator's role
     *
     * @param Auth $auth
     * @return void
     */
    private function denyDeliveryActions(Auth $auth): void
    {
        $user = $auth->getUser();
        self::assertInstanceOf(User::class, $user);
        $roles = [];
        foreach ($user->getRoles() as $role) {
            if (is_scalar($role)) {
                $roles[] = (string) $role;
            }
        }
        self::assertNotSame([], $roles);
        $acl = $this->service(Builder::class)->getAcl();
        $acl->deny($roles, 'Hryvinskyi_DataLayerServerSideAdminUi::delivery');

        self::assertTrue($acl->isAllowed($roles[0], 'Hryvinskyi_DataLayerAdminUi::config'));
        self::assertFalse($acl->isAllowed($roles[0], 'Hryvinskyi_DataLayerServerSideAdminUi::delivery'));
    }
}
