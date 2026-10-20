/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the "Send test event" button: posts the test, shows the reply, and keeps the button disabled while waiting.
 *
 * The root carries `data-hryvinskyi-datalayer-test-event`; its JSON configuration sits in a
 * `script[data-role="config"]` inside it. The request gives up after the configured number of seconds and says so;
 * an answer that is not the endpoint's JSON (an expired session, a denied request) is shown as unreadable.
 */
import {
    parseTestConfig,
    postedFields
} from '@hryvinskyi/data-layer-server-side-admin/delivery-actions/action-config.js';
import {
    describeFailure,
    describeReply,
    describeSending
} from '@hryvinskyi/data-layer-server-side-admin/delivery-actions/test-result.js';

const mounted = new WeakSet();

/**
 * The class of the result area for each state
 *
 * @type {Readonly<Record<string, string>>}
 */
const STATE_CLASSES = Object.freeze({
    sending: 'is-sending',
    valid: 'is-valid',
    messages: 'is-invalid',
    failure: 'is-failed'
});

/**
 * Read the configuration from the root
 *
 * @param {HTMLElement} root
 * @returns {ReturnType<typeof parseTestConfig>}
 */
function readConfig(root) {
    const script = root.querySelector('script[data-role="config"]');
    let raw;
    try {
        raw = JSON.parse(script ? script.textContent || '' : '');
    } catch (error) {
        console.error('The test event configuration is not JSON.', error);

        return null;
    }
    const config = parseTestConfig(raw);
    if (!config) {
        console.error('The test event configuration is incomplete.');
    }

    return config;
}

/**
 * Show a view in the result area
 *
 * @param {HTMLElement} area
 * @param {import('./test-result.js').View} view
 * @returns {void}
 */
function show(area, view) {
    const document = area.ownerDocument;
    const children = [];
    if (view.heading !== '') {
        const heading = document.createElement('strong');
        heading.className = 'hryvinskyi-datalayer-test__heading';
        heading.textContent = view.heading;
        children.push(heading);
    }
    view.lines.forEach((line) => {
        const text = document.createElement('span');
        text.className = 'hryvinskyi-datalayer-test__line';
        text.textContent = line;
        children.push(text);
    });
    Object.values(STATE_CLASSES).forEach((name) => area.classList.remove(name));
    area.classList.add(STATE_CLASSES[view.state]);
    area.replaceChildren(...children);
    area.hidden = false;
}

/**
 * Post the test and describe what came back
 *
 * @param {NonNullable<ReturnType<typeof parseTestConfig>>} config
 * @param {AbortSignal} lifetime Aborted when the widget is torn down
 * @returns {Promise<import('./test-result.js').View>}
 */
async function send(config, lifetime) {
    const deadline = AbortSignal.timeout(config.timeoutSeconds * 1000);
    const signal = typeof AbortSignal.any === 'function' ? AbortSignal.any([lifetime, deadline]) : deadline;
    let response;
    try {
        response = await fetch(config.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: new URLSearchParams([...postedFields(config), ['isAjax', 'true']]),
            signal
        });
    } catch {
        return describeFailure(deadline.aborted ? 'timeout' : 'unreadable', config.wording);
    }
    if (!response.ok) {
        return describeFailure('unreadable', config.wording);
    }
    try {
        return describeReply(await response.json(), config.wording);
    } catch {
        return describeFailure(deadline.aborted ? 'timeout' : 'unreadable', config.wording);
    }
}

/**
 * Bind one test row
 *
 * @param {HTMLElement} root
 * @returns {function(): void} Removes every listener the row added
 */
export function mount(root) {
    if (mounted.has(root)) {
        return () => {};
    }
    const config = readConfig(root);
    const button = root.querySelector('button[data-role="send"]');
    const area = root.querySelector('[data-role="result"]');
    if (!config || !config.testable || !(button instanceof HTMLButtonElement) || !(area instanceof HTMLElement)) {
        return () => {};
    }
    mounted.add(root);

    const controller = new AbortController();
    let busy = false;
    button.disabled = false;
    button.addEventListener('click', async () => {
        if (busy) {
            return;
        }
        busy = true;
        button.disabled = true;
        root.setAttribute('aria-busy', 'true');
        show(area, describeSending(config.wording));
        const view = await send(config, controller.signal);
        busy = false;
        root.removeAttribute('aria-busy');
        if (controller.signal.aborted) {
            return;
        }
        button.disabled = false;
        show(area, view);
    }, {signal: controller.signal});

    return () => {
        controller.abort();
        mounted.delete(root);
    };
}
