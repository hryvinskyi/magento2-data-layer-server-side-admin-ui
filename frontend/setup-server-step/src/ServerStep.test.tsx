/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { act, cleanup, fireEvent, waitFor } from '@testing-library/preact';
import {
    applyPlugin,
    createDraft,
    createWizardRuntime,
    deliveryOf,
    isContractError,
    parsePageConfig,
    requireScopeId,
    requireStepId,
    viewOf,
} from '@hryvinskyi/datalayer-setup';
import register from './index.ts';
import { TEST_DEADLINE_MS } from './use-delivery-actions.ts';
import { pageConfig, withStepData } from './test/fixtures.ts';
import { renderServerStep, stopTimers, type FetchCall, type RenderedStep } from './test/render-step.tsx';

afterEach(async () => {
    cleanup();
    await stopTimers();
});

function lastCall(rendered: RenderedStep): FetchCall {
    const call = rendered.fetch.calls.at(-1);
    assert.ok(call !== undefined, 'a request was sent');
    return call;
}

async function settle(work: () => void): Promise<void> {
    await act(async () => {
        work();
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('the server-side step', () => {
    it('shows the value the wizard holds for a setting, read through the shared SDK', () => {
        const rendered = renderServerStep();
        const input = rendered.getByLabelText('mp_id');
        assert.ok(input instanceof HTMLInputElement);
        assert.equal(input.value, 'G-SAVED1');
        assert.equal(rendered.getByRole('switch', { name: 'ss_enabled' }).getAttribute('aria-checked'), 'true');
    });

    it('shows the Google Analytics settings, then the server container settings when that transport is chosen', async () => {
        const rendered = renderServerStep();
        assert.ok(rendered.queryByLabelText('region') !== null);
        assert.equal(rendered.queryByLabelText('endpoint'), null);

        // The website inherits the transport: it is overridden here first, as a person would.
        const transport = rendered.getByRole('radiogroup', { name: 'transport' });
        const field = transport.closest('.hdl-setup-field');
        assert.ok(field instanceof HTMLElement);
        await settle(() => fireEvent.click(field.querySelector('.hdl-setup-origin__action') ?? field));
        await settle(() => fireEvent.click(rendered.getByRole('radio', { name: /transport\.sgtm/ })));

        assert.ok(rendered.queryByLabelText('endpoint') !== null);
        assert.ok(rendered.queryByLabelText('sgtm_secret') !== null);
        assert.equal(rendered.queryByLabelText('region'), null);
        assert.ok(rendered.queryByLabelText('mp_id') !== null);
    });

    it('shows only the switch, and no tools, where nothing is delivered from the server', async () => {
        const rendered = renderServerStep(withStepData({}, 'website:2'));
        assert.equal(rendered.queryByRole('radiogroup', { name: 'transport' }), null);
        assert.equal(rendered.queryByText('server.tools.title'), null);
        assert.equal(rendered.queryByText('server.consent'), null);
    });

    it('marks the step skipped when the person skips it', async () => {
        const rendered = renderServerStep(withStepData({}, 'website:2'));
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'skip' })));

        const stepper = rendered.getByRole('navigation', { name: 'stepper.label' });
        const serverStep = [...stepper.querySelectorAll('button')].find((button) => button.textContent?.includes('server.step.title'));
        assert.ok(serverStep !== undefined);
        assert.match(serverStep.textContent ?? '', /step\.status\.skipped/);
        assert.equal(rendered.getByRole('heading', { level: 2 }).textContent, 'step.review.title');
    });

    it('sends the test with the unsaved values of the step, leaving out a secret that was not retyped', async () => {
        const rendered = renderServerStep();
        await settle(() => fireEvent.input(rendered.getByLabelText('mp_id'), { target: { value: 'g-draft9' } }));
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.test.send' })));

        const call = lastCall(rendered);
        assert.equal(call.url.pathname, '/server/test');
        assert.equal(call.url.searchParams.get('isAjax'), 'true');
        assert.equal(call.url.searchParams.get('form_key'), 'test-form-key');
        assert.equal(call.init.method, 'POST');
        assert.ok(typeof call.body === 'object' && call.body !== null);
        const body = Object.fromEntries(Object.entries(call.body));
        assert.equal(body['scope'], 'website:1');
        const settings = Object.fromEntries(Object.entries(body['settings'] ?? {}));
        assert.equal(settings['mp_id'], 'g-draft9');
        assert.equal(settings['ss_enabled'], true);
        assert.equal(settings['transport'], 'ga4');
        assert.equal('api_secret' in settings, false);
        assert.equal('enabled' in settings, false);
        const sending = rendered.getByRole('button', { name: 'server.test.sending' });
        assert.equal(sending.getAttribute('aria-busy'), 'true');
        assert.equal(sending.getAttribute('aria-disabled'), 'true', 'busy, yet still focusable');
        fireEvent.click(sending);
        assert.equal(rendered.fetch.calls.length, 1, 'a press while busy sends nothing');
    });

    it('sends a secret the person typed', async () => {
        const rendered = renderServerStep();
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'secret.replace' })));
        await settle(() => fireEvent.input(rendered.getByLabelText('api_secret'), { target: { value: 'typed-secret' } }));
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.test.send' })));

        const settings = Object.fromEntries(Object.entries(Object.fromEntries(Object.entries(lastCall(rendered).body ?? {}))['settings'] ?? {}));
        assert.equal(settings['api_secret'], 'typed-secret');
    });

    it('shows each outcome of the test', async () => {
        const rendered = renderServerStep();
        const send = async (status: number, answer: unknown): Promise<void> => {
            await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.test.send' })));
            await settle(() => lastCall(rendered).answer(status, answer));
        };

        await send(200, { valid: true, messages: [], error: '' });
        assert.ok(rendered.getByText('server.test.valid') !== null);

        await send(200, { valid: false, messages: ['NAME_INVALID · name: too long', 'VALUE_INVALID · client_id'], error: '' });
        assert.ok(rendered.getByText('server.test.messages.other') !== null);
        assert.ok(rendered.getByText('VALUE_INVALID · client_id') !== null);
        assert.equal(rendered.queryByText('server.test.valid'), null);

        await send(200, { valid: false, messages: [], error: 'HTTP 403' });
        assert.ok(rendered.getByText('server.test.failed') !== null);
        assert.match(rendered.getByText('server.test.failed').parentElement?.textContent ?? '', /HTTP 403/);

        await send(500, {});
        assert.ok(rendered.getByText('request.server') !== null);
    });

    it('waits longer for the test than the store waits for its answer', async () => {
        const rendered = renderServerStep();
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.test.send' })));
        assert.ok(TEST_DEADLINE_MS > 10_000);
        assert.equal(lastCall(rendered).init.signal?.aborted, false);
    });

    it('offers no test for a transport without a validation endpoint', async () => {
        const rendered = renderServerStep(withStepData({ testableTransports: [] }));
        assert.ok(rendered.getByRole('button', { name: 'server.test.send' }).hasAttribute('disabled'));
        assert.ok(rendered.getByText('server.test.unavailable') !== null);
    });

    it('offers no action to an administrator without the delivery permission', () => {
        const rendered = renderServerStep(withStepData({ canAct: false }));
        assert.ok(rendered.getByRole('button', { name: 'server.test.send' }).hasAttribute('disabled'));
        assert.ok(rendered.getByText('server.tools.forbidden') !== null);
        assert.equal(rendered.queryByRole('button', { name: 'server.retry' }), null);
    });

    it('offers Retry failed only while events failed, and shows where delivery stands after it', async () => {
        const rendered = renderServerStep();
        assert.ok(rendered.getByText('status.failed_line.2') !== null);
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.retry' })));

        const call = lastCall(rendered);
        assert.equal(call.url.pathname, '/server/retry');
        assert.deepEqual(call.body, { scope: 'website:1' });
        await settle(() => call.answer(200, {
            done: true,
            message: '2 failed events are queued again.',
            status: { pending: 5, sent: 120, failed: 0, progress: 'status.progress', failedLine: '' },
        }));

        assert.equal(rendered.queryByRole('button', { name: 'server.retry' }), null);
        assert.ok(rendered.getByText('2 failed events are queued again.') !== null);
    });

    it('hides Retry failed at a website without failed events', async () => {
        const rendered = renderServerStep(withStepData({}, 'website:2'));
        const enabled = rendered.getByRole('switch', { name: 'ss_enabled' }).closest('.hdl-setup-field');
        assert.ok(enabled instanceof HTMLElement);
        await settle(() => fireEvent.click(enabled.querySelector('.hdl-setup-origin__action') ?? enabled));
        await settle(() => fireEvent.click(rendered.getByRole('switch', { name: 'ss_enabled' })));

        await waitFor(() => assert.ok(rendered.getByText('server.status.title') !== null));
        assert.equal(rendered.queryByRole('button', { name: 'server.retry' }), null);
    });

    it('tells the wizard whether events are delivered from the server, and which step sets it up', () => {
        const rendered = renderServerStep();
        const draft = createDraft(rendered.runtime.config.state);
        assert.deepEqual(deliveryOf(rendered.runtime.serverDelivery, viewOf(draft, requireScopeId('store:1'))), { on: true, stepId: 'server' });
        assert.deepEqual(deliveryOf(rendered.runtime.serverDelivery, viewOf(draft, requireScopeId('website:2'))), { on: false, stepId: 'server' });
        assert.ok(pageConfig['plugins'] !== undefined);
    });
});

describe('the test verdict and the settings it tested', () => {
    it('stops showing a verdict once a setting the test sent changes', async () => {
        const rendered = renderServerStep();
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.test.send' })));
        await settle(() => lastCall(rendered).answer(200, { valid: true, messages: [], error: '' }));
        assert.ok(rendered.getByText('server.test.valid'));

        await settle(() => fireEvent.input(rendered.getByLabelText('mp_id'), { target: { value: 'G-OTHER2' } }));
        assert.equal(rendered.queryByText('server.test.valid'), null, 'the verdict was for the old Measurement ID');

        await settle(() => fireEvent.input(rendered.getByLabelText('mp_id'), { target: { value: 'G-SAVED1' } }));
        assert.ok(rendered.getByText('server.test.valid'), 'back to the tested settings, the verdict applies again');
    });

    it('updates the stepper\'s line for the step as soon as a test answers', async () => {
        const rendered = renderServerStep();
        const stepper = (): HTMLElement => rendered.getByRole('navigation', { name: 'stepper.label' });
        const serverEntry = (): HTMLElement => {
            const entry = [...stepper().querySelectorAll('button')].find((button) => button.textContent?.includes('server.step.title'));
            assert.ok(entry !== undefined);
            return entry;
        };
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'skip' })));
        await settle(() => fireEvent.click(serverEntry()));
        assert.equal(serverEntry().querySelector('.hdl-setup-step__summary')?.textContent, 'transport.ga4');

        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.test.send' })));
        await settle(() => lastCall(rendered).answer(200, { valid: false, messages: ['VALUE_INVALID'], error: '' }));

        assert.equal(serverEntry().querySelector('.hdl-setup-step__summary')?.textContent, 'server.summary.messages.one');
        assert.match(serverEntry().textContent ?? '', /step\.status\.current_attention/);
    });

    it('logs a test answer of the wrong shape, and says the store could not complete the request', async () => {
        const rendered = renderServerStep();
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.test.send' })));
        await settle(() => lastCall(rendered).answer(200, { valid: 'yes', messages: [], error: '' }));

        assert.ok(rendered.getByText('request.server'));
        assert.deepEqual(rendered.logged.map((entry) => entry.message), ['The test event answer could not be read at $.valid']);
    });

    it('logs a retry answer of the wrong shape', async () => {
        const rendered = renderServerStep();
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.retry' })));
        await settle(() => lastCall(rendered).answer(200, { done: true }));

        assert.ok(rendered.getByText('request.server'));
        assert.deepEqual(rendered.logged.map((entry) => entry.message), ['The retry answer could not be read at $.message']);
    });
});

describe('the step\'s page data', () => {
    it('is read once, when the plugin registers: data of the wrong shape keeps the plugin out', () => {
        const runtime = createWizardRuntime(parsePageConfig(withStepData({ canAct: 'yes' })), {
            fetch: () => Promise.reject(new Error('no network')),
            baseUrl: 'https://admin.test/',
            navigator: { go: () => undefined, confirmLeave: () => true },
            logger: { error: () => undefined },
        });
        assert.throws(() => applyPlugin(runtime, register), (error: unknown) => isContractError(error) && error.path === '$.canAct');
        assert.equal(runtime.steps.has(requireStepId('server')), false, 'nothing of the plugin was registered');
    });
});

describe('a replaced secret', () => {
    it('can be taken back, keeping the stored one', async () => {
        const rendered = renderServerStep();
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'secret.replace' })));
        await settle(() => fireEvent.input(rendered.getByLabelText('api_secret'), { target: { value: 'typed-secret' } }));
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'secret.keep' })));

        assert.equal(rendered.getByLabelText('api_secret').hasAttribute('readonly'), true);
        await settle(() => fireEvent.click(rendered.getByRole('button', { name: 'server.test.send' })));
        const settings = Object.fromEntries(Object.entries(Object.fromEntries(Object.entries(lastCall(rendered).body ?? {}))['settings'] ?? {}));
        assert.equal('api_secret' in settings, false, 'the stored secret is used');
    });
});
