/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The step's side panel: send a test event with the values on screen, and where delivery stands at the view's
 * website, with "Retry failed" while any event failed.
 *
 * Both actions need the delivery actions' permission; without it they are not offered and a note says why. A
 * transport that cannot be tested (it has no validation endpoint, so a test would be recorded) disables the test
 * with a note. Every text is the store's wording; what the store answered (findings, errors, the retry's outcome)
 * is shown as the store worded it.
 */
import type { JSX } from 'preact';
import {
    Button,
    Icon,
    Notice,
    Panel,
    useT,
    useWizard,
    type IconName,
    type RequestErrorKind,
    type Translator,
} from '@hryvinskyi/datalayer-setup';
import { transportLabel, transportOf } from './delivery.ts';
import type { StatusView, StepData } from './step-data.ts';
import type { TestOutcome, TestOutcomeKind, TestOutcomes } from './test-outcomes.ts';
import { useRetryFailed, useTestEvent, type RetryState } from './use-delivery-actions.ts';

/** The shell's phrase for each way a request can fail. */
const REQUEST_PHRASE = {
    network: 'request.network',
    timeout: 'request.timeout',
    rejected: 'request.rejected',
    forbidden: 'request.forbidden',
    expired: 'request.expired',
    server: 'request.server',
} as const satisfies Record<RequestErrorKind, string>;

type OutcomeOf<K extends TestOutcomeKind> = Extract<TestOutcome, { readonly kind: K }>;

const failureText = (failure: { readonly kind: RequestErrorKind; readonly serverMessage: string | null }, translator: Translator): string =>
    failure.serverMessage ?? translator.t(REQUEST_PHRASE[failure.kind]);

/** What each test outcome shows under the button. */
const OUTCOME_VIEW: { readonly [K in TestOutcomeKind]: (outcome: OutcomeOf<K>, translator: Translator) => JSX.Element | null } = {
    sending: () => null,
    valid: (_outcome, { t }) => (
        <Notice tone="success" role="status">
            <strong>{t('server.test.valid')}</strong> {t('server.test.valid_text')}
        </Notice>
    ),
    messages: (outcome, translator) => (
        <Notice tone="error">
            <strong>{translator.tn('server.test.messages', outcome.messages.length)}</strong>
            <ul class="hdl-server-test__messages">
                {outcome.messages.map((message, index) => <li key={index}>{message}</li>)}
            </ul>
        </Notice>
    ),
    failed: (outcome, { t }) => (
        <Notice tone="error">
            <strong>{t('server.test.failed')}</strong> {outcome.error}
        </Notice>
    ),
    request: (outcome, translator) => <Notice tone="error">{failureText(outcome.failure, translator)}</Notice>,
};

function OutcomeView<K extends TestOutcomeKind>({ outcome }: { readonly outcome: OutcomeOf<K> }): JSX.Element | null {
    const translator = useT();
    const render: (outcome: OutcomeOf<K>, translator: Translator) => JSX.Element | null = OUTCOME_VIEW[outcome.kind];
    return render(outcome, translator);
}

/** What each state of the retry shows under the status. */
const RETRY_VIEW: { readonly [K in RetryState['kind']]: (state: Extract<RetryState, { readonly kind: K }>, translator: Translator) => JSX.Element | null } = {
    idle: () => null,
    busy: () => null,
    answered: (state) => <Notice tone={state.done ? 'success' : 'error'} role={state.done ? 'status' : 'alert'}>{state.message}</Notice>,
    request: (state, translator) => <Notice tone="error">{failureText(state.failure, translator)}</Notice>,
};

function RetryView<K extends RetryState['kind']>({ state }: { readonly state: Extract<RetryState, { readonly kind: K }> }): JSX.Element | null {
    const translator = useT();
    const render: (state: Extract<RetryState, { readonly kind: K }>, translator: Translator) => JSX.Element | null = RETRY_VIEW[state.kind];
    return render(state, translator);
}

function TestEventTool({ data, outcomes }: { readonly data: StepData; readonly outcomes: TestOutcomes }): JSX.Element {
    const translator = useT();
    const { t } = translator;
    const { view } = useWizard();
    const test = useTestEvent(outcomes, data);
    const testable = data.testableTransports.has(transportOf(view));
    const sending = test.outcome?.kind === 'sending';
    return (
        <div class="hdl-server-tools__test">
            <Button icon="send" onClick={test.send} disabled={!data.canAct || !testable} busy={sending}>
                {sending ? t('server.test.sending') : t('server.test.send')}
            </Button>
            {data.canAct && testable ? <p class="hdl-server-tools__note">{t('server.test.note')}</p> : null}
            {testable ? null : (
                <Notice tone="info" icon="block">{t('server.test.unavailable', transportLabel(view))}</Notice>
            )}
            {test.outcome === undefined ? null : <OutcomeView outcome={test.outcome} />}
        </div>
    );
}

function DeliveryCount({ icon, label, value, failing = false }: {
    readonly icon: IconName;
    readonly label: string;
    readonly value: string;
    readonly failing?: boolean;
}): JSX.Element {
    return (
        <div class="hdl-server-status__count" data-failing={failing ? 'true' : 'false'}>
            <dt class="hdl-server-status__label"><Icon name={icon} size={14} />{label}</dt>
            <dd class="hdl-server-status__value">{value}</dd>
        </div>
    );
}

function DeliveryStatus({ status, canAct, retry }: {
    readonly status: StatusView;
    readonly canAct: boolean;
    readonly retry: ReturnType<typeof useRetryFailed>;
}): JSX.Element {
    const translator = useT();
    const { t, n } = translator;
    return (
        <section class="hdl-server-status" aria-labelledby="hdl-server-status-title">
            <h4 id="hdl-server-status-title" class="hdl-server-status__title">{t('server.status.title')}</h4>
            <dl class="hdl-server-status__counts">
                <DeliveryCount icon="schedule" label={t('server.status.pending')} value={n(status.pending)} />
                <DeliveryCount icon="check" label={t('server.status.sent')} value={n(status.sent)} />
                <DeliveryCount icon="error" label={t('server.status.failed')} value={n(status.failed)} failing={status.failed > 0} />
            </dl>
            <p class="hdl-server-status__line">{status.progress}</p>
            {status.failed > 0 ? (
                <div class="hdl-server-status__failed">
                    <span class="hdl-server-status__failed-text">{status.failedLine}</span>
                    {canAct ? (
                        <Button variant="tertiary" icon="refresh" onClick={retry.retry} busy={retry.state.kind === 'busy'}>
                            {t('server.retry')}
                        </Button>
                    ) : null}
                </div>
            ) : null}
            <RetryView state={retry.state} />
        </section>
    );
}

export function DeliveryTools({ data, outcomes }: { readonly data: StepData; readonly outcomes: TestOutcomes }): JSX.Element {
    const { t } = useT();
    const retry = useRetryFailed(data);
    return (
        <Panel title={t('server.tools.title')}>
            <div class="hdl-server-tools">
                <TestEventTool data={data} outcomes={outcomes} />
                {data.canAct ? null : <Notice tone="info" icon="lock">{t('server.tools.forbidden')}</Notice>}
                {retry.status === undefined ? null : <DeliveryStatus status={retry.status} canAct={data.canAct} retry={retry} />}
            </div>
        </Panel>
    );
}
