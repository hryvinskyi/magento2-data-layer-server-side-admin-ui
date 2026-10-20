/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Registers the server-side delivery settings' validation rules with the settings form's validator, with their
 * wording.
 *
 * A mixin of the validator module, so the rules exist whenever a form can be validated. The checks themselves live in
 * the pure rules module; this file only names them and gives each its message.
 */
define([
    'jquery',
    'mage/translate',
    'Hryvinskyi_DataLayerServerSideAdminUi/js/validation/rules'
], function ($, $t, rules) {
    'use strict';

    return function (validation) {
        $.validator.addMethod(
            'validate-hryvinskyi-https-origin',
            rules.isHttpsOrigin,
            $t('Enter an https:// address with no path or query, for example https://sst.example.com.')
        );
        $.validator.addMethod(
            'validate-hryvinskyi-whole-number-range',
            function (value, element) {
                return rules.isWholeNumberInRange(value, rules.rangeOf(element.className));
            },
            function (parameters, element) {
                var range = rules.rangeOf(element.className) || {minimum: 0, maximum: 0};

                return $t('Enter a whole number from %1 to %2.')
                    .replace('%1', String(range.minimum))
                    .replace('%2', String(range.maximum));
            }
        );

        return validation;
    };
});
