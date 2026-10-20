/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the "Retry failed" button: posts the retry as a form of its own, outside the settings form.
 *
 * The settings page is one form, and a form cannot hold another, so the button builds a hidden form at the end of
 * the document with the form key and the website, and submits it; the endpoint answers with the settings page and a
 * message. The button is disabled after the first click, so the retry is posted once.
 */
import {
    parseRetryConfig,
    postedFields
} from '@hryvinskyi/data-layer-server-side-admin/delivery-actions/action-config.js';

const mounted = new WeakSet();

/**
 * Read the configuration from the root
 *
 * @param {HTMLElement} root
 * @returns {ReturnType<typeof parseRetryConfig>}
 */
function readConfig(root) {
    const script = root.querySelector('script[data-role="config"]');
    let raw;
    try {
        raw = JSON.parse(script ? script.textContent || '' : '');
    } catch (error) {
        console.error('The retry configuration is not JSON.', error);

        return null;
    }
    const config = parseRetryConfig(raw);
    if (!config) {
        console.error('The retry configuration is incomplete.');
    }

    return config;
}

/**
 * Build the form that posts the retry
 *
 * @param {Document} document
 * @param {NonNullable<ReturnType<typeof parseRetryConfig>>} config
 * @returns {HTMLFormElement}
 */
function retryForm(document, config) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = config.url;
    form.hidden = true;
    postedFields(config).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.append(input);
    });

    return form;
}

/**
 * Bind one retry button
 *
 * @param {HTMLElement} root
 * @returns {function(): void} Removes the listener the button added
 */
export function mount(root) {
    if (mounted.has(root)) {
        return () => {};
    }
    const config = readConfig(root);
    const button = root.querySelector('button[data-role="retry"]');
    if (!config || !(button instanceof HTMLButtonElement)) {
        return () => {};
    }
    mounted.add(root);

    const controller = new AbortController();
    button.disabled = false;
    button.addEventListener('click', () => {
        button.disabled = true;
        const form = retryForm(root.ownerDocument, config);
        root.ownerDocument.body.append(form);
        form.submit();
    }, {signal: controller.signal, once: true});

    return () => {
        controller.abort();
        mounted.delete(root);
    };
}
