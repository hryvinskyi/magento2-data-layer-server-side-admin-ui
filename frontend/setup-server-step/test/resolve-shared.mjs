/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The test-time twin of the page's import map: one Preact and one SDK for every file a test loads.
 *
 * The SDK's source sits in another package, so its own `import 'preact'` would resolve from that package's
 * dependencies while this plugin's components resolve from this one's: two copies of Preact, and hooks fail across
 * them. Plain Node does not read the TypeScript `paths` either. This hook sends `preact`, `preact/hooks` and
 * `preact/jsx-runtime`, from any importer, to this package's copy, and `@hryvinskyi/datalayer-setup` to the SDK's
 * source, as the page's import map sends them to the one published copy of each.
 */
import { registerHooks } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const PACKAGE = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SDK = resolve(PACKAGE, '../../../DataLayerAdminUi/frontend/setup-wizard/src/sdk/index.ts');
const SHARED_PREACT = new Set(['preact', 'preact/hooks', 'preact/jsx-runtime']);
const OWN_PARENT = pathToFileURL(resolve(PACKAGE, 'test/resolve-shared.mjs')).href;

registerHooks({
    resolve: (specifier, context, nextResolve) => {
        if (specifier === '@hryvinskyi/datalayer-setup') {
            return { url: pathToFileURL(SDK).href, shortCircuit: true };
        }
        if (SHARED_PREACT.has(specifier)) {
            return nextResolve(specifier, { ...context, parentURL: OWN_PARENT });
        }
        return nextResolve(specifier, context);
    },
});
