/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The step's two requests, bound to the component: send a test event with the unsaved settings, and queue failed
 * events again.
 *
 * Both go through the wizard's JSON client (its request rules, a deadline, error kinds). A newer request aborts the
 * one before it, leaving the step aborts whatever is in flight, and an aborted request's answer is dropped. Neither
 * is retried: a person clicks again. The test waits longer than the store's own wait for Google's answer, so a slow
 * answer is reported by the store rather than cut off here. An answer of the wrong shape is a bug between the two
 * sides: it goes to the log, and the person sees the failure as the store not completing the request.
 */
import { useEffect, useMemo, useRef, useState } from 'preact/hooks';
import {
    failureOf,
    isContractError,
    parseTestResult,
    useJsonClient,
    useLogger,
    useWizard,
    type Logger,
    type RequestFailure,
    type ScopeId,
} from '@hryvinskyi/datalayer-setup';
import { deliveryScope } from './delivery.ts';
import { parseRetryAnswer, type StepData, type StatusView } from './step-data.ts';
import { outcomeOf, settingsKey, testedSettings, type TestOutcome, type TestOutcomes } from './test-outcomes.ts';

/** How long the test may take: the store waits up to ten seconds for Google, and then answers. */
export const TEST_DEADLINE_MS = 20_000;

/** The failure to show for a request; an answer of the wrong shape is logged first, naming where it broke. */
function failureLogged(error: unknown, logger: Logger, what: string): RequestFailure {
    if (isContractError(error)) {
        logger.error(`The ${what} answer could not be read at ${error.path}`, error);
    }
    return failureOf(error);
}

interface AbortSlot {
    /** Aborts the request before and starts a new one. */
    next(): AbortController;
    /** Whether the request is still the latest one. */
    isCurrent(controller: AbortController): boolean;
}

/** One request at a time, aborted by the next one and when the component goes away. */
function useAbortSlot(): AbortSlot {
    const current = useRef<AbortController | null>(null);
    useEffect(() => () => current.current?.abort(), []);
    return useMemo((): AbortSlot => ({
        next: (): AbortController => {
            current.current?.abort();
            const controller = new AbortController();
            current.current = controller;
            return controller;
        },
        isCurrent: (controller: AbortController): boolean => current.current === controller,
    }), []);
}

export interface TestEventBinding {
    /** The last test of the view's website, while the settings it sent are still the ones on screen. */
    readonly outcome: TestOutcome | undefined;
    send(): void;
}

export function useTestEvent(outcomes: TestOutcomes, data: StepData): TestEventBinding {
    const { view } = useWizard();
    const client = useJsonClient();
    const logger = useLogger();
    const slot = useAbortSlot();
    const [, changed] = useState(0);
    useEffect(() => outcomes.subscribe(() => changed((count) => count + 1)), [outcomes]);
    const scope = deliveryScope(view);
    const current = settingsKey(view);

    const send = (): void => {
        const controller = slot.next();
        const tested: ScopeId = scope;
        const sent = current;
        outcomes.set(tested, sent, { kind: 'sending' });
        const body = { scope: view.scope.id, settings: testedSettings(view) };
        client.post(data.testUrl, body, { signal: controller.signal, timeoutMs: TEST_DEADLINE_MS })
            .then((answer) => {
                if (!controller.signal.aborted) {
                    outcomes.set(tested, sent, outcomeOf(parseTestResult(answer)));
                }
            })
            .catch((error: unknown) => {
                if (controller.signal.aborted) {
                    // Left mid-way rather than replaced by a newer test: nothing is being sent any more.
                    if (slot.isCurrent(controller) && outcomes.at(tested, sent)?.kind === 'sending') {
                        outcomes.forget(tested);
                    }
                    return;
                }
                outcomes.set(tested, sent, { kind: 'request', failure: failureLogged(error, logger, 'test event') });
            });
    };

    return { outcome: outcomes.at(scope, current), send };
}

export type RetryState =
    | { readonly kind: 'idle' }
    | { readonly kind: 'busy' }
    | { readonly kind: 'answered'; readonly done: boolean; readonly message: string }
    | { readonly kind: 'request'; readonly failure: RequestFailure };

export interface RetryBinding {
    readonly state: RetryState;
    /** Where delivery stands at the view's website: the latest answer's, else the one the page came with. */
    readonly status: StatusView | undefined;
    retry(): void;
}

interface RetryEntry {
    readonly state: RetryState;
    readonly status?: StatusView;
}

const IDLE: RetryState = { kind: 'idle' };

export function useRetryFailed(data: StepData): RetryBinding {
    const { view } = useWizard();
    const client = useJsonClient();
    const logger = useLogger();
    const slot = useAbortSlot();
    const [entries, setEntries] = useState<ReadonlyMap<ScopeId, RetryEntry>>(new Map());
    const scope = deliveryScope(view);
    const entry = entries.get(scope);

    const record = (at: ScopeId, next: RetryEntry): void =>
        setEntries((current) => new Map(current).set(at, next));

    const retry = (): void => {
        const controller = slot.next();
        const retried: ScopeId = scope;
        const status = entry?.status;
        record(retried, { state: { kind: 'busy' }, ...(status === undefined ? {} : { status }) });
        client.post(data.retryUrl, { scope: view.scope.id }, { signal: controller.signal })
            .then((answer) => {
                if (!controller.signal.aborted) {
                    const parsed = parseRetryAnswer(answer);
                    record(retried, { state: { kind: 'answered', done: parsed.done, message: parsed.message }, status: parsed.status });
                }
            })
            .catch((error: unknown) => {
                if (!controller.signal.aborted) {
                    const failure = failureLogged(error, logger, 'retry');
                    record(retried, { state: { kind: 'request', failure }, ...(status === undefined ? {} : { status }) });
                }
            });
    };

    return { state: entry?.state ?? IDLE, status: entry?.status ?? data.status.get(scope), retry };
}
