/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Turns the test endpoint's reply into what the result area shows. Pure: no DOM, no globals.
 *
 * The reply is `{valid, messages, error}` and holds exactly one outcome: valid, validation messages, or an error. A
 * reply of any other shape cannot be trusted and is shown as unreadable. Every sentence comes from the wording the
 * page printed, translated; this module adds none of its own.
 */

/**
 * @typedef {{valid: boolean, messages: string[], error: string}} Reply
 * @typedef {'sending'|'valid'|'messages'|'failure'} State
 * @typedef {{state: State, heading: string, lines: string[]}} View
 * @typedef {{sending: string, validHeading: string, validText: string, messageHeading: string,
 *     messagesHeading: string, failureHeading: string, timeout: string, unreadable: string}} Wording
 */

/**
 * The wording a failure without a reply shows, by why there is no reply
 *
 * @type {Readonly<Record<string, keyof Wording>>}
 */
const FAILURE_WORDING = Object.freeze({
    timeout: 'timeout',
    unreadable: 'unreadable'
});

const WORDING_KEYS = Object.freeze([
    'sending',
    'validHeading',
    'validText',
    'messageHeading',
    'messagesHeading',
    'failureHeading',
    'timeout',
    'unreadable'
]);

/**
 * @param {*} value
 * @returns {boolean} Whether a value is an object (not an array, not null)
 */
function isObject(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Read the wording the page printed
 *
 * @param {*} value
 * @returns {Wording|null} Null when a sentence is missing or not text
 */
export function parseWording(value) {
    if (!isObject(value) || !WORDING_KEYS.every((key) => typeof value[key] === 'string')) {
        return null;
    }

    return /** @type {Wording} */ (Object.freeze(Object.fromEntries(WORDING_KEYS.map((key) => [key, value[key]]))));
}

/**
 * Read the endpoint's reply
 *
 * @param {*} value The decoded JSON
 * @returns {Reply|null} Null when the reply is not one of the three outcomes
 */
export function parseReply(value) {
    if (!isObject(value) || typeof value.valid !== 'boolean' || typeof value.error !== 'string'
        || !Array.isArray(value.messages) || !value.messages.every((message) => typeof message === 'string')
    ) {
        return null;
    }
    const outcomes = Number(value.valid) + Number(value.messages.length > 0) + Number(value.error !== '');
    if (outcomes !== 1) {
        return null;
    }

    return Object.freeze({valid: value.valid, messages: Object.freeze([...value.messages]), error: value.error});
}

/**
 * What the area shows while the request is on its way
 *
 * @param {Wording} wording
 * @returns {View}
 */
export function describeSending(wording) {
    return Object.freeze({state: 'sending', heading: '', lines: Object.freeze([wording.sending])});
}

/**
 * What the area shows when no reply came back, or none that can be read
 *
 * @param {string} reason `timeout` or `unreadable`
 * @param {Wording} wording
 * @returns {View}
 */
export function describeFailure(reason, wording) {
    const key = Object.hasOwn(FAILURE_WORDING, reason) ? FAILURE_WORDING[reason] : 'unreadable';

    return Object.freeze({state: 'failure', heading: wording.failureHeading, lines: Object.freeze([wording[key]])});
}

/**
 * What the area shows for the endpoint's reply
 *
 * @param {*} value The decoded JSON
 * @param {Wording} wording
 * @returns {View}
 */
export function describeReply(value, wording) {
    const reply = parseReply(value);
    if (!reply) {
        return describeFailure('unreadable', wording);
    }
    if (reply.valid) {
        return Object.freeze({state: 'valid', heading: wording.validHeading, lines: Object.freeze([wording.validText])});
    }
    if (reply.messages.length > 0) {
        const heading = reply.messages.length === 1
            ? wording.messageHeading
            : wording.messagesHeading.split('{count}').join(String(reply.messages.length));

        return Object.freeze({state: 'messages', heading, lines: reply.messages});
    }

    return Object.freeze({state: 'failure', heading: wording.failureHeading, lines: Object.freeze([reply.error])});
}
