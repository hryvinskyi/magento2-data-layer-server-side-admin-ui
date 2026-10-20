/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The shapes the server-side delivery settings accept, mirroring what the server checks when they are saved. Pure:
 * no DOM, no jQuery, no wording.
 *
 * Every check accepts an empty value: whether a setting is required is decided elsewhere. Values are trimmed first,
 * as the server trims them.
 *
 * An AMD module because the settings form's validator is reached only through RequireJS.
 */
define([], function () {
    'use strict';

    var HTTPS_ORIGIN = /^https:\/\/[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?\/?$/i,
        WHOLE_NUMBER = /^\d+$/,
        RANGE_CLASS = /(?:^|\s)whole-number-range-(\d+)-(\d+)(?:\s|$)/;

    /**
     * @param {*} value
     * @returns {string} The value as trimmed text
     */
    function text(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    /**
     * Whether a value is empty or the https address of a host, with at most a port and a trailing slash
     *
     * @param {*} value
     * @returns {boolean}
     */
    function isHttpsOrigin(value) {
        var trimmed = text(value);

        return trimmed === '' || HTTPS_ORIGIN.test(trimmed);
    }

    /**
     * Read the bounds a field's classes give it, written as `whole-number-range-<minimum>-<maximum>`
     *
     * @param {*} className
     * @returns {{minimum: number, maximum: number}|null} Null when the classes name no bounds
     */
    function rangeOf(className) {
        var match = RANGE_CLASS.exec(typeof className === 'string' ? className : '');

        return match ? {minimum: Number(match[1]), maximum: Number(match[2])} : null;
    }

    /**
     * Whether a value is empty or a whole number within the bounds
     *
     * @param {*} value
     * @param {{minimum: number, maximum: number}|null} range Without bounds, any whole number
     * @returns {boolean}
     */
    function isWholeNumberInRange(value, range) {
        var trimmed = text(value),
            number;

        if (trimmed === '') {
            return true;
        }
        if (!WHOLE_NUMBER.test(trimmed)) {
            return false;
        }
        number = Number(trimmed);

        return range === null || number >= range.minimum && number <= range.maximum;
    }

    return Object.freeze({
        isHttpsOrigin: isHttpsOrigin,
        rangeOf: rangeOf,
        isWholeNumberInRange: isWholeNumberInRange
    });
});
