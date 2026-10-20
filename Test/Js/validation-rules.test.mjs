/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The checks behind the server-side settings' validation rules, loaded the way RequireJS loads them: the AMD module
 * runs in a sandbox whose `define` hands back what its factory returns. Plain Node:
 * node --test app/code/Hryvinskyi/DataLayerServerSideAdminUi/Test/Js/
 */
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';

const moduleUrl = new URL('../../view/adminhtml/web/js/validation/rules.js', import.meta.url);

/**
 * Run the AMD module and return its value
 *
 * @returns {Promise<object>}
 */
async function loadRules() {
    const source = await readFile(moduleUrl, 'utf8');
    let exported;
    vm.runInNewContext(source, {
        define: (dependencies, factory) => {
            assert.equal(dependencies.length, 0);
            exported = factory();
        }
    }, {filename: moduleUrl.pathname});

    return exported;
}

const rules = await loadRules();

test('an endpoint is empty or the https address of a host, with at most a port and a trailing slash', () => {
    for (const value of ['', 'https://sst.example.com', 'https://sst.example.com/', ' https://SST.example.com:8443 ']) {
        assert.equal(rules.isHttpsOrigin(value), true, value);
    }
    for (const value of [
        'http://sst.example.com',
        'https://sst.example.com/metrics',
        'https://sst.example.com/?api_secret=x',
        'https://user@sst.example.com',
        'sst.example.com',
        'https://'
    ]) {
        assert.equal(rules.isHttpsOrigin(value), false, value);
    }
});

test('the bounds come from the field class written as whole-number-range-<minimum>-<maximum>', () => {
    assert.deepEqual(
        {...rules.rangeOf('input-text validate-x whole-number-range-30-1000')},
        {minimum: 30, maximum: 1000}
    );
    assert.equal(rules.rangeOf('input-text whole-number-range-x-2'), null);
    assert.equal(rules.rangeOf(undefined), null);
});

test('a whole number must be within its bounds; an empty value passes', () => {
    const range = rules.rangeOf('whole-number-range-1-20');

    for (const value of ['', '1', ' 8 ', '20']) {
        assert.equal(rules.isWholeNumberInRange(value, range), true, value);
    }
    for (const value of ['0', '21', '2.5', '-3', 'eight', '1e1']) {
        assert.equal(rules.isWholeNumberInRange(value, range), false, value);
    }
});

test('without bounds, any whole number passes', () => {
    assert.equal(rules.isWholeNumberInRange('400', null), true);
    assert.equal(rules.isWholeNumberInRange('4.5', null), false);
});
