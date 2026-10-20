/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * How the step sums itself up and when it asks for a look, from the draft and the last test event of the view's
 * website, while that test still speaks for the settings on screen.
 *
 * - The summary is "Browser only" while nothing is delivered from the server; otherwise the transport's label, with
 *   the last test's verdict when it found the event valid or reported findings.
 * - The step needs attention while the last test reported findings or could not check the event.
 */
import type { StepSummaryContext, Translator } from '@hryvinskyi/datalayer-setup';
import { deliveryScope, isDeliveryOn, transportLabel } from './delivery.ts';
import { settingsKey, type TestOutcome, type TestOutcomeKind, type TestOutcomes } from './test-outcomes.ts';

type OutcomeOf<K extends TestOutcomeKind> = Extract<TestOutcome, { readonly kind: K }>;

/** The summary line for each test outcome, given the transport's label. */
const SUMMARY: { readonly [K in TestOutcomeKind]: (label: string, outcome: OutcomeOf<K>, translator: Translator) => string } = {
    sending: (label) => label,
    valid: (label, _outcome, translator) => translator.t('server.summary.valid', label),
    messages: (label, outcome, translator) => translator.tn('server.summary.messages', outcome.messages.length, label),
    failed: (label) => label,
    request: (label) => label,
};

const NEEDS_LOOK: Readonly<Record<TestOutcomeKind, boolean>> = Object.freeze({
    sending: false,
    valid: false,
    messages: true,
    failed: true,
    request: false,
});

function summaryLine<K extends TestOutcomeKind>(label: string, outcome: OutcomeOf<K>, translator: Translator): string {
    const describe: (label: string, outcome: OutcomeOf<K>, translator: Translator) => string = SUMMARY[outcome.kind];
    return describe(label, outcome, translator);
}

export function summaryOf(outcomes: TestOutcomes): (context: StepSummaryContext) => string {
    return ({ view, t }) => {
        if (!isDeliveryOn(view)) {
            return t.t('server.summary.off');
        }
        const label = transportLabel(view);
        const outcome = outcomes.at(deliveryScope(view), settingsKey(view));
        return outcome === undefined ? label : summaryLine(label, outcome, t);
    };
}

export function attentionOf(outcomes: TestOutcomes): (context: StepSummaryContext) => boolean {
    return ({ view }) => {
        const outcome = outcomes.at(deliveryScope(view), settingsKey(view));
        return isDeliveryOn(view) && outcome !== undefined && NEEDS_LOOK[outcome.kind];
    };
}
