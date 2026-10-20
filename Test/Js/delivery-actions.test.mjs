/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The delivery actions' pure parts: reading the printed configuration, the fields posted, and turning the test
 * endpoint's reply into what the result area shows. Plain Node: node --test app/code/Hryvinskyi/DataLayerServerSideAdminUi/Test/Js/
 */
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {
    describeFailure,
    describeReply,
    describeSending,
    parseReply,
    parseWording
} from '../../view/adminhtml/web/js/delivery-actions/test-result.js';
import './import-map.mjs';

const {
    parseRetryConfig,
    parseTestConfig,
    postedFields
} = await import('../../view/adminhtml/web/js/delivery-actions/action-config.js');

const WORDING = {
    sending: 'Sending…',
    validHeading: 'Valid',
    validText: 'Google returned no validation messages.',
    messageHeading: 'Google returned 1 validation message:',
    messagesHeading: 'Google returned {count} validation messages:',
    failureHeading: 'Not checked',
    timeout: 'No answer within 20 seconds. Try again later.',
    unreadable: 'The answer could not be read. Reload the page and try again.'
};

const TEST_CONFIG = {
    url: 'https://admin.example.com/backend/hryvinskyi_datalayer_server/delivery/testEvent/',
    formKey: 'Fk3y',
    website: 2,
    testable: true,
    timeoutSeconds: 20,
    wording: WORDING
};

test('a valid reply shows the valid heading and the line saying nothing was found', () => {
    assert.deepEqual(describeReply({valid: true, messages: [], error: ''}, WORDING), {
        state: 'valid',
        heading: 'Valid',
        lines: ['Google returned no validation messages.']
    });
});

test('validation messages are listed under a heading that counts them, one message in the singular', () => {
    const two = [
        'VALUE_INVALID · client_id: must be a non-empty string.',
        'NAME_INVALID · event name "add-to-cart": use letters, digits and underscores.'
    ];

    assert.deepEqual(describeReply({valid: false, messages: two, error: ''}, WORDING), {
        state: 'messages',
        heading: 'Google returned 2 validation messages:',
        lines: two
    });
    assert.deepEqual(describeReply({valid: false, messages: [two[0]], error: ''}, WORDING), {
        state: 'messages',
        heading: 'Google returned 1 validation message:',
        lines: [two[0]]
    });
});

test('an error is shown under the failure heading as the server wrote it', () => {
    assert.deepEqual(describeReply({valid: false, messages: [], error: 'HTTP 403'}, WORDING), {
        state: 'failure',
        heading: 'Not checked',
        lines: ['HTTP 403']
    });
});

test('a reply that is not exactly one outcome, or not the endpoint\'s shape, is unreadable', () => {
    const unreadable = {state: 'failure', heading: 'Not checked', lines: [WORDING.unreadable]};
    const replies = [
        null,
        'Access denied',
        [],
        {ajaxExpired: 1, ajaxRedirect: 'https://admin.example.com/'},
        {valid: true, messages: ['X · y: z'], error: ''},
        {valid: true, messages: [], error: 'HTTP 500'},
        {valid: false, messages: [], error: ''},
        {valid: false, messages: [1], error: ''},
        {valid: 'yes', messages: [], error: ''}
    ];

    replies.forEach((reply) => {
        assert.equal(parseReply(reply), null, JSON.stringify(reply));
        assert.deepEqual(describeReply(reply, WORDING), unreadable, JSON.stringify(reply));
    });
});

test('without a reply the failure says whether time ran out; an unknown reason reads as unreadable', () => {
    assert.deepEqual(describeFailure('timeout', WORDING).lines, [WORDING.timeout]);
    assert.deepEqual(describeFailure('unreadable', WORDING).lines, [WORDING.unreadable]);
    assert.deepEqual(describeFailure('toString', WORDING).lines, [WORDING.unreadable]);
    assert.equal(describeFailure('timeout', WORDING).state, 'failure');
});

test('while waiting the area shows the sending line without a heading', () => {
    assert.deepEqual(describeSending(WORDING), {state: 'sending', heading: '', lines: ['Sending…']});
});

test('the wording needs every sentence as text', () => {
    assert.deepEqual(parseWording(WORDING), WORDING);
    assert.equal(parseWording({...WORDING, timeout: 20}), null);
    const {validText, ...missing} = WORDING;
    assert.equal(validText, WORDING.validText);
    assert.equal(parseWording(missing), null);
});

test('the test configuration is read whole or refused', () => {
    assert.deepEqual(parseTestConfig(TEST_CONFIG), TEST_CONFIG);
    [
        {...TEST_CONFIG, url: ''},
        {...TEST_CONFIG, formKey: 7},
        {...TEST_CONFIG, website: -1},
        {...TEST_CONFIG, website: '2'},
        {...TEST_CONFIG, testable: 'true'},
        {...TEST_CONFIG, timeoutSeconds: 0},
        {...TEST_CONFIG, timeoutSeconds: 1000},
        {...TEST_CONFIG, wording: {}},
        null
    ].forEach((config) => assert.equal(parseTestConfig(config), null, JSON.stringify(config)));
});

test('the retry configuration needs the endpoint, the form key and the website; the default scope is 0', () => {
    const config = {url: 'https://admin.example.com/backend/retry/', formKey: 'Fk3y', website: 0};

    assert.deepEqual(parseRetryConfig(config), config);
    assert.equal(parseRetryConfig({...config, website: 1.5}), null);
    assert.equal(parseRetryConfig({...config, url: undefined}), null);
});

test('both actions post the form key and the website, as text', () => {
    assert.deepEqual(postedFields({url: 'u', formKey: 'Fk3y', website: 0}), [['form_key', 'Fk3y'], ['website', '0']]);
    assert.deepEqual(postedFields(TEST_CONFIG), [['form_key', 'Fk3y'], ['website', '2']]);
});
