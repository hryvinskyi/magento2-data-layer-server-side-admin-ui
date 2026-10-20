/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The outcome of the last test event at each scope whose settings it tested, kept for the page: the step shows it,
 * and the step's summary and attention read it after the person moved on.
 *
 * An outcome speaks only for the settings it was sent with. Once a setting the test sent changes, the outcome is
 * no longer the answer for what is on screen, so it is not reported any more (and a test sent again replaces it).
 *
 * One store per wizard page, created when the plugin registers; nothing here is module-level state.
 */
import { draftValues, type RequestFailure, type ScopeId, type SettingsView, type TestResult } from '@hryvinskyi/datalayer-setup';
import { serverSettingKeys } from './delivery.ts';

export type TestOutcome =
    | { readonly kind: 'sending' }
    | { readonly kind: 'valid' }
    | { readonly kind: 'messages'; readonly messages: readonly string[] }
    /** The store answered but could not check the event (a setting is missing, no answer came back). */
    | { readonly kind: 'failed'; readonly error: string }
    /** The request itself failed (session ended, store unreachable …). */
    | { readonly kind: 'request'; readonly failure: RequestFailure };

export type TestOutcomeKind = TestOutcome['kind'];

/** The outcome a test result stands for: valid, findings, or an error. */
export function outcomeOf(result: TestResult): TestOutcome {
    if (result.valid) {
        return { kind: 'valid' };
    }
    if (result.messages.length > 0) {
        return { kind: 'messages', messages: result.messages };
    }
    return { kind: 'failed', error: result.error };
}

/** What a test event sends for the view: every setting of the step, as the draft has it there. */
export const testedSettings = (view: SettingsView): ReturnType<typeof draftValues> => draftValues(view, serverSettingKeys(view));

/** Names the settings a test was sent with, so an outcome can be matched to the settings on screen. */
export const settingsKey = (view: SettingsView): string => JSON.stringify(testedSettings(view));

export interface TestOutcomes {
    /** The last outcome at the scope, while the settings it was sent with (`sent`, a `settingsKey`) still apply. */
    at(scope: ScopeId, sent: string): TestOutcome | undefined;
    set(scope: ScopeId, sent: string, outcome: TestOutcome): void;
    /** Drops the scope's outcome, as if it was never tested. */
    forget(scope: ScopeId): void;
    /** Calls `listener` after every change; returns the unsubscribe. */
    subscribe(listener: () => void): () => void;
}

interface Tested {
    readonly sent: string;
    readonly outcome: TestOutcome;
}

export function createTestOutcomes(): TestOutcomes {
    const outcomes = new Map<ScopeId, Tested>();
    const listeners = new Set<() => void>();
    const announce = (): void => listeners.forEach((listener) => listener());
    return Object.freeze({
        at: (scope: ScopeId, sent: string): TestOutcome | undefined => {
            const tested = outcomes.get(scope);
            return tested !== undefined && tested.sent === sent ? tested.outcome : undefined;
        },
        set: (scope: ScopeId, sent: string, outcome: TestOutcome): void => {
            outcomes.set(scope, { sent, outcome });
            announce();
        },
        forget: (scope: ScopeId): void => {
            if (outcomes.delete(scope)) {
                announce();
            }
        },
        subscribe: (listener: () => void): (() => void) => {
            listeners.add(listener);
            return () => void listeners.delete(listener);
        },
    });
}
