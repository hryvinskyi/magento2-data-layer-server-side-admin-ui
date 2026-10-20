/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The setup wizard's server-side step, as a wizard plugin: the page imports this module by its name in the import
 * map and calls its default export with the SDK.
 *
 * It adds the step after the products step (optional, so it offers "Skip this step"), the required rules of each
 * transport, and the answer to "is anything delivered from the server here?", which the wizard's own steps ask
 * without knowing this step. Everything it keeps for the page (the last test event of each website) is created here,
 * once per page, and its page data is read here, once: data of the wrong shape keeps the step out of the page (the
 * wizard logs why) instead of breaking it while it renders.
 */
import { PRODUCTS_STEP, type PluginRegister } from '@hryvinskyi/datalayer-setup';
import { isDeliveryOn, requirementRules } from './delivery.ts';
import { PLUGIN_ID, SERVER_STEP } from './keys.ts';
import { createServerStep } from './ServerStep.tsx';
import { parseStepData } from './step-data.ts';
import { attentionOf, summaryOf } from './step-behaviour.ts';
import { createTestOutcomes } from './test-outcomes.ts';

const register: PluginRegister = (sdk) => {
    const data = parseStepData(sdk.extensionData(PLUGIN_ID));
    const outcomes = createTestOutcomes();
    sdk.registerStep({
        id: SERVER_STEP,
        after: PRODUCTS_STEP,
        icon: 'dns',
        title: 'server.step.title',
        intro: 'server.step.intro',
        skippable: true,
        component: createServerStep(outcomes, data),
        summary: summaryOf(outcomes),
        attention: attentionOf(outcomes),
        subscribe: outcomes.subscribe,
    });
    requirementRules().forEach(([key, rule]) => sdk.registerRequirement(key, rule));
    sdk.registerServerDelivery({ stepId: SERVER_STEP, isOn: isDeliveryOn });
};

export default register;
