/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * A page configuration as the server prints it, holding this step's settings beside one wizard setting, and the
 * step's page data. Every text a person would read is its own key (a phrase's text is the phrase key, a setting's
 * label is the setting key, an option's label is `<setting>.<value>`), so a test finds an element by the key.
 *
 * Scopes: the default scope, website 1 with store view 1, website 2 with store view 2. Server-side delivery is off
 * by default and on at website 1, which delivers through Google Analytics with a measurement ID and a saved API
 * secret; website 1 has two failed events, website 2 none.
 */

type Json = null | boolean | number | string | readonly Json[] | { readonly [key: string]: Json };
export type Config = { readonly [key: string]: Json };

/** The step's phrase keys; a phrase the step uses that is not listed fails the test. */
export const SERVER_PHRASES: readonly string[] = [
    'server.step.title',
    'server.step.intro',
    'server.skipped',
    'server.card.delivery',
    'server.card.identity',
    'server.card.orders',
    'server.card.queue',
    'server.help.ss_enabled',
    'server.help.mp_id',
    'server.help.endpoint',
    'server.help.user_id',
    'server.help.purchase_trigger',
    'server.placeholder.mp_id',
    'server.placeholder.endpoint',
    'server.unit.days',
    'server.transport.ga4.description',
    'server.transport.sgtm.description',
    'server.debug.warning',
    'server.consent',
    'server.tools.title',
    'server.tools.forbidden',
    'server.test.send',
    'server.test.sending',
    'server.test.note',
    'server.test.valid',
    'server.test.valid_text',
    'server.test.messages.one',
    'server.test.messages.other',
    'server.test.failed',
    'server.test.unavailable',
    'server.status.title',
    'server.status.pending',
    'server.status.sent',
    'server.status.failed',
    'server.retry',
    'server.summary.off',
    'server.summary.valid',
    'server.summary.messages.one',
    'server.summary.messages.other',
];

const SCOPES = ['default', 'website:1', 'store:1', 'website:2', 'store:2'];

const perScope = (prefix: string): Config => Object.fromEntries(SCOPES.map((scope) => [scope, `${prefix}/${scope}`]));

const option = (key: string, value: string): Config => ({ value, label: `${key}.${value}` });

interface SettingSpec {
    readonly key: string;
    readonly kind: string;
    readonly builtIn: Json;
    readonly step?: string;
    readonly reach?: string;
    readonly options?: readonly string[];
    readonly values?: Config;
    readonly extra?: Config;
}

function setting(spec: SettingSpec): Config {
    return {
        key: spec.key,
        path: `test/${spec.key}/value`,
        kind: spec.kind,
        step: spec.step ?? 'server',
        label: spec.key,
        options: (spec.options ?? []).map((value) => option(spec.key, value)),
        reach: spec.reach ?? 'website',
        builtIn: spec.builtIn,
        values: spec.values ?? {},
        locks: {},
        ...spec.extra,
    };
}

const MEASUREMENT_PATTERN: Config = { pattern: { source: '^G-[A-Z0-9]+$', flags: '' }, caseInsensitive: true };
const ENDPOINT_PATTERN: Config = { pattern: { source: '^https://[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\\d{1,5})?/?$', flags: 'i' } };

const status = (failed: number): Config => ({
    pending: 3,
    sent: 120,
    failed,
    progress: 'status.progress',
    failedLine: failed > 0 ? `status.failed_line.${failed}` : '',
});

export const STEP_DATA: Config = {
    testUrl: '/server/test',
    retryUrl: '/server/retry',
    canAct: true,
    testableTransports: ['ga4'],
    transportFields: { ga4: ['mp_id', 'api_secret', 'region'], sgtm: ['mp_id', 'endpoint', 'sgtm_secret'] },
    transportIcons: { ga4: 'analytics', sgtm: 'dns' },
    status: { default: status(0), 'website:1': status(2), 'website:2': status(0) },
};

export const pageConfig: Config = {
    endpoints: { save: '/save', preview: '/preview', products: '/products', session: '/session', advanced: perScope('/advanced'), close: perScope('/close') },
    formKey: 'test-form-key',
    locale: 'en_US',
    phrases: Object.fromEntries(SERVER_PHRASES.map((key) => [key, key])),
    plugins: [{ id: 'server', module: '@hryvinskyi/datalayer-setup-server' }],
    initialScope: 'website:1',
    state: {
        scopes: [
            { id: 'default', type: 'default', name: 'Default', code: 'default', parent: null },
            { id: 'website:1', type: 'website', name: 'Main', code: 'main', parent: 'default' },
            { id: 'store:1', type: 'store', name: 'English', code: 'en', parent: 'website:1' },
            { id: 'website:2', type: 'website', name: 'Trade', code: 'trade', parent: 'default' },
            { id: 'store:2', type: 'store', name: 'Trade view', code: 'trade_en', parent: 'website:2' },
        ],
        settings: [
            setting({ key: 'enabled', kind: 'flag', builtIn: true, step: 'products', reach: 'store' }),
            setting({ key: 'ss_enabled', kind: 'flag', builtIn: false, values: { 'website:1': true } }),
            setting({ key: 'transport', kind: 'choice', builtIn: 'ga4', options: ['ga4', 'sgtm'] }),
            setting({ key: 'mp_id', kind: 'text', builtIn: '', values: { 'website:1': 'G-SAVED1' }, extra: MEASUREMENT_PATTERN }),
            setting({ key: 'api_secret', kind: 'secret', builtIn: { set: false }, values: { 'website:1': { set: true } } }),
            setting({ key: 'region', kind: 'choice', builtIn: 'global', options: ['global', 'eu'] }),
            setting({ key: 'endpoint', kind: 'text', builtIn: '', extra: ENDPOINT_PATTERN }),
            setting({ key: 'sgtm_secret', kind: 'secret', builtIn: { set: false } }),
            setting({ key: 'user_id', kind: 'flag', builtIn: false }),
            setting({ key: 'first_party', kind: 'flag', builtIn: true }),
            setting({ key: 'visitor_days', kind: 'number', builtIn: '400', extra: { min: 30, max: 1000 } }),
            setting({ key: 'purchase_trigger', kind: 'choice', builtIn: 'placed', options: ['paid', 'placed'] }),
            setting({ key: 'debug', kind: 'flag', builtIn: false }),
            setting({ key: 'max_attempts', kind: 'number', builtIn: '8', extra: { min: 1, max: 20 } }),
        ],
        events: { catalogue: [], presets: [], builtIn: { off: [], channels: {} }, values: {}, locks: {} },
    },
    extensions: { server: STEP_DATA },
};

/** The page configuration with other step data, or opened at another scope. */
export function withStepData(patch: Config, initialScope = 'website:1'): Config {
    return { ...pageConfig, initialScope, extensions: { server: { ...STEP_DATA, ...patch } } };
}
