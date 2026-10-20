/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The server-side step: whether events are delivered from the server, and when they are, through which transport
 * with which credentials, how visitors are identified, when a purchase is sent and how delivery retries; beside the
 * fields, the test event and the delivery status.
 *
 * Which settings a transport shows comes from the step's page data, so the step names no transport. Settings kept
 * per website show as locked at a store view (the kit does that from each setting's reach). A step the person
 * skipped while delivery is off says so above the fields.
 */
import type { ComponentType, JSX } from 'preact';
import {
    Card,
    Columns,
    flagOf,
    MessageStrip,
    Notice,
    useSetting,
    useStepNavigation,
    useT,
    useWizard,
} from '@hryvinskyi/datalayer-setup';
import { isDeliveryOn, transportOf } from './delivery.ts';
import { DeliveryTools } from './DeliveryTools.tsx';
import { ServerField, TransportField } from './fields.tsx';
import {
    DEBUG,
    FIRST_PARTY,
    MAX_ATTEMPTS,
    PURCHASE_TRIGGER,
    SERVER_STEP,
    SS_ENABLED,
    USER_ID,
    VISITOR_DAYS,
} from './keys.ts';
import type { StepData } from './step-data.ts';
import type { TestOutcomes } from './test-outcomes.ts';

function ServerStep({ outcomes, data }: { readonly outcomes: TestOutcomes; readonly data: StepData }): JSX.Element {
    const { t } = useT();
    const { view } = useWizard();
    const debug = useSetting(DEBUG);
    const navigation = useStepNavigation();
    const on = isDeliveryOn(view);
    const skipped = navigation.steps.some((item) => item.step.id === SERVER_STEP && item.progress === 'skipped');
    const transportFields = data.transportFields.get(transportOf(view)) ?? [];

    return (
        <>
            {skipped && !on ? <MessageStrip tone="info" role="status">{t('server.skipped')}</MessageStrip> : null}
            <Columns aside={on ? <DeliveryTools data={data} outcomes={outcomes} /> : undefined}>
                <Card icon="dns" title={t('server.card.delivery')}>
                    <ServerField settingKey={SS_ENABLED} />
                    {on ? <TransportField data={data} /> : null}
                    {on ? transportFields.map((key) => <ServerField key={key} settingKey={key} />) : null}
                </Card>
                {on ? (
                    <>
                        <Card icon="fingerprint" title={t('server.card.identity')}>
                            <ServerField settingKey={USER_ID} />
                            <ServerField settingKey={FIRST_PARTY} />
                            <ServerField settingKey={VISITOR_DAYS} />
                        </Card>
                        <Card icon="receipt_long" title={t('server.card.orders')}>
                            <ServerField settingKey={PURCHASE_TRIGGER} />
                        </Card>
                        <Card icon="local_shipping" title={t('server.card.queue')}>
                            <ServerField settingKey={DEBUG} />
                            {flagOf(debug.value) ? <Notice tone="warning">{t('server.debug.warning')}</Notice> : null}
                            <ServerField settingKey={MAX_ATTEMPTS} />
                        </Card>
                        <Notice tone="info">{t('server.consent')}</Notice>
                    </>
                ) : null}
            </Columns>
        </>
    );
}

/** The step's component, bound to the page's test outcomes and the step's page data. */
export function createServerStep(outcomes: TestOutcomes, data: StepData): ComponentType {
    return function ServerStepView(): JSX.Element {
        return <ServerStep outcomes={outcomes} data={data} />;
    };
}
