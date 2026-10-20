/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { isContractError, requireScopeId } from '@hryvinskyi/datalayer-setup';
import { parseRetryAnswer, parseStepData } from './step-data.ts';
import { STEP_DATA } from './test/fixtures.ts';

const refusedAt = (path: string) => (error: unknown): boolean => isContractError(error) && error.path === path;

describe('the step data', () => {
    it('reads what the server sends', () => {
        const data = parseStepData(STEP_DATA);
        assert.equal(data.canAct, true);
        assert.deepEqual([...data.testableTransports], ['ga4']);
        assert.deepEqual(data.transportFields.get('sgtm'), ['mp_id', 'endpoint', 'sgtm_secret']);
        assert.equal(data.transportIcons.get('ga4'), 'analytics');
        assert.equal(data.status.get(requireScopeId('website:1'))?.failed, 2);
    });

    it('accepts empty maps as empty objects', () => {
        const data = parseStepData({ ...STEP_DATA, transportFields: {}, transportIcons: {} });
        assert.equal(data.transportFields.size, 0);
        assert.equal(data.transportIcons.size, 0);
    });

    it('refuses a shape the two sides did not agree on, naming the path', () => {
        assert.throws(() => parseStepData({ ...STEP_DATA, canAct: 'yes' }), refusedAt('$.canAct'));
        assert.throws(() => parseStepData({ ...STEP_DATA, transportFields: { ga4: ['Not A Key'] } }), refusedAt('$.transportFields.ga4[0]'));
        assert.throws(() => parseStepData({ ...STEP_DATA, status: { 'shop:1': {} } }), refusedAt('$.status.shop:1'));
        assert.throws(
            () => parseStepData({ ...STEP_DATA, status: { default: { pending: -1, sent: 0, failed: 0, progress: '', failedLine: '' } } }),
            refusedAt('$.status.default.pending'),
        );
    });

    it('reads the answer of a retry', () => {
        const answer = parseRetryAnswer({ done: false, message: 'm', status: { pending: 0, sent: 1, failed: 2, progress: 'p', failedLine: 'f' } });
        assert.deepEqual(answer, { done: false, message: 'm', status: { pending: 0, sent: 1, failed: 2, progress: 'p', failedLine: 'f' } });
        assert.throws(() => parseRetryAnswer({ done: true, message: 'm' }), refusedAt('$.status'));
    });
});
