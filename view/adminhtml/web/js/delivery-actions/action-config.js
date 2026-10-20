/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Reads the configuration the delivery actions' markup carries, and the fields they post. Pure: no DOM, no globals.
 *
 * Both actions post to an admin endpoint with the form key and the website the settings page shows (0 at the default
 * scope). A configuration of any other shape is refused, so a broken page leaves its button disabled.
 */
import {parseWording} from '@hryvinskyi/data-layer-server-side-admin/delivery-actions/test-result.js';

const MAX_TIMEOUT_SECONDS = 120;

/**
 * @typedef {{url: string, formKey: string, website: number}} PostTarget
 * @typedef {PostTarget & {testable: boolean, timeoutSeconds: number,
 *     wording: import('./test-result.js').Wording}} TestConfig
 */

/**
 * @param {*} value
 * @returns {boolean} Whether a value is an object (not an array, not null)
 */
function isObject(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * @param {*} value
 * @returns {PostTarget|null}
 */
function parseTarget(value) {
    if (!isObject(value) || typeof value.url !== 'string' || value.url === ''
        || typeof value.formKey !== 'string' || value.formKey === ''
        || !Number.isInteger(value.website) || value.website < 0
    ) {
        return null;
    }

    return {url: value.url, formKey: value.formKey, website: value.website};
}

/**
 * Read the configuration of the "Retry failed" button
 *
 * @param {*} value The decoded JSON
 * @returns {Readonly<PostTarget>|null}
 */
export function parseRetryConfig(value) {
    const target = parseTarget(value);

    return target ? Object.freeze(target) : null;
}

/**
 * Read the configuration of the "Send test event" button
 *
 * @param {*} value The decoded JSON
 * @returns {Readonly<TestConfig>|null}
 */
export function parseTestConfig(value) {
    const target = parseTarget(value);
    const wording = isObject(value) ? parseWording(value.wording) : null;
    if (!target || !wording || typeof value.testable !== 'boolean' || !Number.isInteger(value.timeoutSeconds)
        || value.timeoutSeconds < 1 || value.timeoutSeconds > MAX_TIMEOUT_SECONDS
    ) {
        return null;
    }

    return Object.freeze({...target, testable: value.testable, timeoutSeconds: value.timeoutSeconds, wording});
}

/**
 * The fields an action posts
 *
 * @param {PostTarget} target
 * @returns {Array<[string, string]>} Name and value pairs, in order
 */
export function postedFields(target) {
    return [['form_key', target.formKey], ['website', String(target.website)]];
}
