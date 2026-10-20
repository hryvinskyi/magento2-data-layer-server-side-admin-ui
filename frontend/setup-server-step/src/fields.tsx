/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The step's fields. Each setting is drawn by the control of its kind (a switch, a select or radio list, a text
 * input, a secret input) with the look this step gives it: help line, placeholder, width, unit. Label, options,
 * scope tag, origin line and problems come from the setting itself, through the kit's `SettingField`.
 *
 * The transport is drawn apart, as cards: each option's label comes from the server, its glyph from the step's page
 * data and its description from the step's wording, all keyed by the transport's code.
 */
import type { JSX } from 'preact';
import {
    iconOr,
    RadioCards,
    SecretInput,
    SelectInput,
    SettingField,
    Switch,
    textOf,
    TextInput,
    useSetting,
    useT,
    type ControlWidth,
    type FieldControl,
    type SettingBinding,
    type SettingKey,
    type SettingKind,
    type Translator,
} from '@hryvinskyi/datalayer-setup';
import {
    DEBUG,
    ENDPOINT,
    FIRST_PARTY,
    MAX_ATTEMPTS,
    MP_ID,
    PURCHASE_TRIGGER,
    REGION,
    SGTM_SECRET,
    SS_ENABLED,
    API_SECRET,
    TRANSPORT,
    USER_ID,
    VISITOR_DAYS,
} from './keys.ts';
import type { StepData } from './step-data.ts';

type ChoiceStyle = 'select' | 'radios';

/** How this step draws one setting. Texts are phrase keys. */
interface FieldLook {
    readonly help?: string;
    readonly placeholder?: string;
    readonly suffix?: string;
    readonly width?: ControlWidth;
    readonly mono?: boolean;
    readonly choice?: ChoiceStyle;
}

const LOOKS: Readonly<Record<string, FieldLook>> = Object.freeze({
    [SS_ENABLED]: { help: 'server.help.ss_enabled' },
    [MP_ID]: { help: 'server.help.mp_id', placeholder: 'server.placeholder.mp_id', width: 'medium', mono: true },
    [API_SECRET]: { width: 'medium' },
    [REGION]: { choice: 'select', width: 'long' },
    [ENDPOINT]: { help: 'server.help.endpoint', placeholder: 'server.placeholder.endpoint', mono: true },
    [SGTM_SECRET]: { width: 'medium' },
    [USER_ID]: { help: 'server.help.user_id' },
    [FIRST_PARTY]: { help: 'server.help.first_party' },
    [VISITOR_DAYS]: { width: 'short', suffix: 'server.unit.days' },
    [PURCHASE_TRIGGER]: { help: 'server.help.purchase_trigger', choice: 'radios' },
    [DEBUG]: {},
    [MAX_ATTEMPTS]: { width: 'short' },
});

const NO_LOOK: FieldLook = Object.freeze({});

interface ControlProps {
    readonly setting: SettingBinding;
    readonly control: FieldControl;
    readonly look: FieldLook;
    readonly translator: Translator;
}

const phraseOf = (translator: Translator, key: string | undefined): string | undefined =>
    (key === undefined ? undefined : translator.t(key));

function TextControl({ setting, control, look, translator }: ControlProps): JSX.Element {
    return (
        <TextInput
            id={control.id}
            value={textOf(setting.value)}
            onInput={(text) => setting.set(text)}
            onBlur={control.onBlur}
            disabled={control.disabled}
            invalid={control.invalid}
            describedBy={control.describedBy}
            placeholder={phraseOf(translator, look.placeholder)}
            suffix={phraseOf(translator, look.suffix)}
            mono={look.mono ?? false}
            width={look.width ?? 'long'}
        />
    );
}

const CHOICES: { readonly [S in ChoiceStyle]: (props: ControlProps) => JSX.Element } = {
    select: ({ setting, control, look }) => (
        <SelectInput
            id={control.id}
            value={textOf(setting.value)}
            options={setting.definition.options}
            onChange={(value) => setting.set(value)}
            disabled={control.disabled}
            invalid={control.invalid}
            describedBy={control.describedBy}
            width={look.width ?? 'long'}
        />
    ),
    radios: ({ setting, control }) => (
        <RadioCards
            name={control.id}
            label={setting.definition.label}
            value={textOf(setting.value)}
            options={setting.definition.options}
            onChange={(value) => setting.set(value)}
            disabled={control.disabled}
            describedBy={control.describedBy}
            variant="plain"
        />
    ),
};

/** The control of each kind of setting. */
const CONTROLS: { readonly [K in SettingKind]: (props: ControlProps) => JSX.Element } = {
    flag: ({ setting, control }) => (
        <Switch
            id={control.id}
            checked={setting.value === true}
            onChange={(checked) => setting.set(checked)}
            disabled={control.disabled}
            describedBy={control.describedBy}
        />
    ),
    choice: (props) => CHOICES[props.look.choice ?? 'select'](props),
    text: TextControl,
    number: TextControl,
    secret: ({ setting, control, look }) => (
        <SecretInput
            id={control.id}
            value={setting.value}
            onReplace={(text) => setting.set({ replace: text })}
            onKeep={setting.replacing ? setting.discard : undefined}
            onBlur={control.onBlur}
            disabled={control.disabled}
            invalid={control.invalid}
            describedBy={control.describedBy}
            width={look.width ?? 'medium'}
        />
    ),
};

export function ServerField({ settingKey }: { readonly settingKey: SettingKey }): JSX.Element {
    const translator = useT();
    const setting = useSetting(settingKey);
    const look = LOOKS[settingKey] ?? NO_LOOK;
    const Control = CONTROLS[setting.definition.kind];
    return (
        <SettingField
            setting={setting}
            help={phraseOf(translator, look.help)}
            labelFor={!(setting.definition.kind === 'choice' && look.choice === 'radios')}
        >
            {(control) => <Control setting={setting} control={control} look={look} translator={translator} />}
        </SettingField>
    );
}

export function TransportField({ data }: { readonly data: StepData }): JSX.Element {
    const translator = useT();
    const setting = useSetting(TRANSPORT);
    const options = setting.definition.options.map((option) => ({
        value: option.value,
        label: option.label,
        description: translator.t(`server.transport.${option.value}.description`),
        icon: iconOr(data.transportIcons.get(option.value), 'dns'),
    }));
    return (
        <SettingField setting={setting} labelFor={false}>
            {(control) => (
                <RadioCards
                    name={control.id}
                    label={setting.definition.label}
                    value={textOf(setting.value)}
                    options={options}
                    onChange={(value) => setting.set(value)}
                    disabled={control.disabled}
                    describedBy={control.describedBy}
                />
            )}
        </SettingField>
    );
}
