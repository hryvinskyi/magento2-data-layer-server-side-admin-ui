/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The rules of server-side delivery as the wizard's draft stands. Server-side settings are kept per website: at a
 * store view the website's values apply, at the default scope the default ones.
 *
 * The required rules mirror what the store needs before it can deliver: a Google Analytics transport needs a
 * measurement ID and an API secret, a server container needs its address. The store checks again when it sends;
 * these only give feedback in the wizard.
 */
import {
    DEFAULT_SCOPE,
    flagOf,
    textOf,
    type RequirementRule,
    type ScopeId,
    type SettingKey,
    type SettingsView,
} from '@hryvinskyi/datalayer-setup';
import { API_SECRET, ENDPOINT, MP_ID, SERVER_STEP, SS_ENABLED, TRANSPORT } from './keys.ts';

/** The scope whose server-side settings apply here: the view's website, or the default scope. */
export function deliveryScope(view: SettingsView): ScopeId {
    return view.tree.websiteOf(view.scope.id) ?? DEFAULT_SCOPE;
}

/** Whether events are delivered from the server at the view's website, once the edits apply. */
export function isDeliveryOn(view: SettingsView): boolean {
    return flagOf(view.valueAt(SS_ENABLED, deliveryScope(view)));
}

/** The transport code at the view's website, once the edits apply; empty when the state holds none. */
export function transportOf(view: SettingsView): string {
    return textOf(view.valueAt(TRANSPORT, deliveryScope(view)));
}

/** What each transport cannot deliver without. */
const REQUIRED_BY_TRANSPORT: Readonly<Record<string, readonly SettingKey[]>> = Object.freeze({
    ga4: [MP_ID, API_SECRET],
    sgtm: [ENDPOINT],
});

/** Each required setting with the rule saying when it is required: while delivery is on through its transport. */
export function requirementRules(): readonly (readonly [SettingKey, RequirementRule])[] {
    return Object.entries(REQUIRED_BY_TRANSPORT).flatMap(([transport, keys]) => keys.map(
        (key): readonly [SettingKey, RequirementRule] => [
            key,
            (view: SettingsView): boolean => isDeliveryOn(view) && transportOf(view) === transport,
        ],
    ));
}

/** The keys of every setting this step owns, in the server's order. */
export function serverSettingKeys(view: SettingsView): readonly SettingKey[] {
    return view.definitions().filter((definition) => definition.step === SERVER_STEP).map((definition) => definition.key);
}

/** The translated label of the view's transport, from the setting's own options; the code when none matches. */
export function transportLabel(view: SettingsView): string {
    const code = transportOf(view);
    return view.definition(TRANSPORT)?.options.find((option) => option.value === code)?.label ?? code;
}
