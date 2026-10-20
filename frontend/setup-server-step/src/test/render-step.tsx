/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Mounts the server-side step as the page mounts a plugin: the page configuration parsed by the SDK, a runtime
 * built by the SDK, the wizard's products and review steps standing in around the plugin, the plugin registered
 * through the SDK's `applyPlugin`, and the step rendered inside the SDK's provider beside the SDK's stepper and a
 * "skip" button driven by the SDK's step navigation, as the wizard's shell drives it.
 *
 * The network is a fake `fetch` that records each request and answers only when the test settles it. A phrase of
 * this step that the fixture does not list fails the test.
 */
import { render, type RenderResult } from '@testing-library/preact';
import type { JSX } from 'preact';
import {
    applyPlugin,
    createWizardRuntime,
    parsePageConfig,
    PRODUCTS_STEP,
    REVIEW_STEP,
    Stepper,
    useStepNavigation,
    useT,
    WizardProvider,
    type HttpResponse,
    type WizardRuntime,
} from '@hryvinskyi/datalayer-setup';
import register from '../index.ts';
import { SERVER_STEP } from '../keys.ts';
import { pageConfig, SERVER_PHRASES, type Config } from './fixtures.ts';

export interface FetchCall {
    readonly url: URL;
    readonly init: RequestInit;
    /** The JSON body the request carried. */
    readonly body: unknown;
    answer(status: number, body: unknown): void;
    fail(error: unknown): void;
}

export interface RecordingFetch {
    readonly calls: FetchCall[];
    fetch(url: string, init: RequestInit): Promise<HttpResponse>;
}

export function recordingFetch(): RecordingFetch {
    const calls: FetchCall[] = [];
    return {
        calls,
        fetch: (url: string, init: RequestInit): Promise<HttpResponse> => new Promise((resolve, reject) => {
            const body: unknown = typeof init.body === 'string' ? JSON.parse(init.body) : null;
            init.signal?.addEventListener('abort', () => reject(init.signal?.reason), { once: true });
            calls.push({
                url: new URL(url),
                init,
                body,
                answer: (status, answer) => resolve({
                    status,
                    redirected: false,
                    text: () => Promise.resolve(JSON.stringify(answer)),
                }),
                fail: reject,
            });
        }),
    };
}

const Empty = (): null => null;

/**
 * Ends what the page left running: the DOM's timers outlive the test (every request starts a deadline timer), and
 * would keep the test process waiting for them.
 */
export async function stopTimers(): Promise<void> {
    const page: unknown = Reflect.get(globalThis, 'happyDOM');
    const abort: unknown = typeof page === 'object' && page !== null ? Reflect.get(page, 'abort') : undefined;
    if (typeof abort === 'function') {
        await Promise.resolve(abort.call(page));
    }
}

function Host(): JSX.Element {
    const { t } = useT();
    const navigation = useStepNavigation();
    const Step = navigation.current.step.component;
    return (
        <div>
            <Stepper narrow={false} />
            <h2>{t(navigation.current.step.title)}</h2>
            {navigation.current.step.skippable === true ? <button type="button" onClick={navigation.skip}>skip</button> : null}
            <Step />
        </div>
    );
}

export type RenderedStep = RenderResult & {
    readonly fetch: RecordingFetch;
    readonly runtime: WizardRuntime;
    /** What the page logged, in order. */
    readonly logged: { readonly message: string; readonly detail: unknown }[];
};

export function renderServerStep(config: Config = pageConfig): RenderedStep {
    const fetch = recordingFetch();
    const logged: { readonly message: string; readonly detail: unknown }[] = [];
    const runtime = createWizardRuntime(parsePageConfig(config), {
        fetch: fetch.fetch,
        baseUrl: 'https://admin.test/',
        navigator: { go: () => undefined, confirmLeave: () => true },
        logger: { error: (message: string, detail?: unknown): void => void logged.push({ message, detail }) },
        onMissingPhrase: (key: string): void => {
            if (key.startsWith('server.') && !SERVER_PHRASES.includes(key)) {
                throw new Error(`Missing phrase: ${key}`);
            }
        },
    });
    runtime.sdk.registerStep({ id: PRODUCTS_STEP, icon: 'sell', title: 'step.products.title', component: Empty });
    applyPlugin(runtime, register);
    runtime.sdk.registerStep({ id: REVIEW_STEP, icon: 'fact_check', title: 'step.review.title', component: Empty });

    const rendered = render(
        <WizardProvider runtime={runtime} initialStep={SERVER_STEP}>
            <Host />
        </WizardProvider>,
    );
    return Object.assign(rendered, { fetch, runtime, logged });
}
