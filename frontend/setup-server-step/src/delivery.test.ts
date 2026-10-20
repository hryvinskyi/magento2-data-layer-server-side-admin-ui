/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    createDraft,
    createRequirementRegistry,
    parsePageConfig,
    requireScopeId,
    setValue,
    stepIssues,
    viewOf,
    type Draft,
} from '@hryvinskyi/datalayer-setup';
import { deliveryScope, isDeliveryOn, requirementRules, serverSettingKeys, transportLabel, transportOf } from './delivery.ts';
import { API_SECRET, ENDPOINT, MP_ID, SERVER_STEP, SS_ENABLED, TRANSPORT } from './keys.ts';
import { createTestOutcomes, settingsKey } from './test-outcomes.ts';
import { attentionOf, summaryOf } from './step-behaviour.ts';
import { pageConfig } from './test/fixtures.ts';

const scope = requireScopeId;

function draft(): Draft {
    return createDraft(parsePageConfig(pageConfig).state);
}

function edited(base: Draft, at: string, key: Parameters<typeof setValue>[2], value: Parameters<typeof setValue>[3]): Draft {
    const result = setValue(base, scope(at), key, value);
    assert.ok(result.ok, `the edit of ${key} at ${at} is accepted`);
    return result.value;
}

function registry(): ReturnType<typeof createRequirementRegistry> {
    const requirements = createRequirementRegistry();
    requirementRules().forEach(([key, rule]) => requirements.register(key, rule));
    return requirements;
}

const translator = {
    t: (key: string, ...params: readonly unknown[]): string => [key, ...params.map(String)].join('|'),
    tn: (key: string, count: number, ...params: readonly unknown[]): string => [key, String(count), ...params.map(String)].join('|'),
    n: (value: number): string => String(value),
    list: (values: readonly string[]): string => values.join(','),
    issue: (key: string, code: string): string => `${key}.${code}`,
};

describe('server-side delivery rules', () => {
    it('reads delivery at the website of a store view and at the default scope for the default scope', () => {
        const base = draft();
        assert.equal(deliveryScope(viewOf(base, scope('store:1'))), 'website:1');
        assert.equal(deliveryScope(viewOf(base, scope('default'))), 'default');
        assert.equal(isDeliveryOn(viewOf(base, scope('store:1'))), true);
        assert.equal(isDeliveryOn(viewOf(base, scope('store:2'))), false);
        assert.equal(isDeliveryOn(viewOf(base, scope('default'))), false);
        assert.equal(isDeliveryOn(viewOf(edited(base, 'website:2', SS_ENABLED, true), scope('store:2'))), true);
    });

    it('names the transport with its own option label', () => {
        const view = viewOf(draft(), scope('website:1'));
        assert.equal(transportOf(view), 'ga4');
        assert.equal(transportLabel(view), 'transport.ga4');
    });

    it('requires a measurement ID and an API secret for Google Analytics, an address for a server container', () => {
        const blank = edited(draft(), 'website:1', MP_ID, '');
        assert.deepEqual(stepIssues(SERVER_STEP, viewOf(blank, scope('website:1')), registry()), [{ key: MP_ID, code: 'required' }]);

        // An inherited value is not the scope's to fix, so the container is set up at the default scope.
        const container = edited(edited(draft(), 'default', SS_ENABLED, true), 'default', TRANSPORT, 'sgtm');
        assert.deepEqual(stepIssues(SERVER_STEP, viewOf(container, scope('default')), registry()), [{ key: ENDPOINT, code: 'required' }]);
    });

    it('counts a secret as present when it is saved or typed, and requires nothing while delivery is off', () => {
        const replaced = edited(draft(), 'website:1', API_SECRET, { replace: 'typed' });
        assert.deepEqual(stepIssues(SERVER_STEP, viewOf(replaced, scope('website:1')), registry()), []);
        assert.deepEqual(stepIssues(SERVER_STEP, viewOf(draft(), scope('default')), registry()), []);
    });

    it('lists the settings of its own step only', () => {
        const keys = serverSettingKeys(viewOf(draft(), scope('website:1')));
        assert.equal(keys.includes(MP_ID), true);
        assert.equal(keys.some((key) => key === 'enabled'), false);
        assert.equal(keys.length, 13);
    });
});

describe('step summary and attention', () => {
    it('says the browser alone sends while delivery is off', () => {
        const summary = summaryOf(createTestOutcomes());
        const view = viewOf(draft(), scope('website:2'));
        assert.equal(summary({ view, t: translator, delivery: { on: false, stepId: null } }), 'server.summary.off');
    });

    it('names the transport, with the verdict of the last test of the website', () => {
        const outcomes = createTestOutcomes();
        const summary = summaryOf(outcomes);
        const attention = attentionOf(outcomes);
        const view = viewOf(draft(), scope('store:1'));
        const context = { view, t: translator, delivery: { on: true, stepId: SERVER_STEP } };

        const sent = settingsKey(view);
        assert.equal(summary(context), 'transport.ga4');
        outcomes.set(scope('website:1'), sent, { kind: 'valid' });
        assert.equal(summary(context), 'server.summary.valid|transport.ga4');
        assert.equal(attention(context), false);
        outcomes.set(scope('website:1'), sent, { kind: 'messages', messages: ['a', 'b'] });
        assert.equal(summary(context), 'server.summary.messages|2|transport.ga4');
        assert.equal(attention(context), true);
        outcomes.forget(scope('website:1'));
        assert.equal(attention(context), false);
    });

    it('drops the verdict once a setting the test sent changes, and has it back when the setting does', () => {
        const outcomes = createTestOutcomes();
        const summary = summaryOf(outcomes);
        const attention = attentionOf(outcomes);
        const tested = viewOf(draft(), scope('website:1'));
        outcomes.set(scope('website:1'), settingsKey(tested), { kind: 'messages', messages: ['a'] });

        const edited = setValue(draft(), scope('website:1'), MP_ID, 'G-OTHER2');
        assert.ok(edited.ok);
        const changed = { view: viewOf(edited.value, scope('website:1')), t: translator, delivery: { on: true, stepId: SERVER_STEP } };
        assert.equal(summary(changed), 'transport.ga4', 'no verdict for settings that were not tested');
        assert.equal(attention(changed), false);

        const back = { view: tested, t: translator, delivery: { on: true, stepId: SERVER_STEP } };
        assert.equal(attention(back), true);
    });

    it('tells whoever listens when an outcome changes', () => {
        const outcomes = createTestOutcomes();
        let heard = 0;
        const stop = outcomes.subscribe(() => void heard++);
        outcomes.set(scope('website:1'), 'sent', { kind: 'valid' });
        outcomes.forget(scope('website:1'));
        outcomes.forget(scope('website:1'));
        stop();
        outcomes.set(scope('website:1'), 'sent', { kind: 'valid' });
        assert.equal(heard, 2, 'a change each, nothing for a scope without an outcome, nothing after unsubscribing');
    });
});
