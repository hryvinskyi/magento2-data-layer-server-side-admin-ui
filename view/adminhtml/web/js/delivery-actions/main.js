/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Entry point of the delivery actions: binds every "Retry failed" and "Send test event" button on the settings page
 * once the page is parsed.
 */
import {mount as mountRetry} from '@hryvinskyi/data-layer-server-side-admin/delivery-actions/retry-failed.js';
import {mount as mountTest} from '@hryvinskyi/data-layer-server-side-admin/delivery-actions/test-event.js';

/**
 * Which widget binds the roots marked with each attribute
 *
 * @type {ReadonlyArray<[string, function(HTMLElement): function(): void]>}
 */
const WIDGETS = Object.freeze([
    ['[data-hryvinskyi-datalayer-retry-failed]', mountRetry],
    ['[data-hryvinskyi-datalayer-test-event]', mountTest]
]);

/**
 * Bind every delivery action under a scope
 *
 * @param {ParentNode} scope
 * @returns {void}
 */
function mountAll(scope) {
    WIDGETS.forEach(([selector, mount]) => {
        scope.querySelectorAll(selector).forEach((root) => {
            if (root instanceof HTMLElement) {
                mount(root);
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mountAll(document), {once: true});
} else {
    mountAll(document);
}
