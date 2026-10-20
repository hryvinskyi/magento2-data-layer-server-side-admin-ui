/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the server sends this step besides its settings, and what its two endpoints answer, parsed from `unknown`
 * into the step's own types. A field of the wrong shape is a bug between the two sides: it throws the SDK's
 * `ContractError` naming the path.
 */
import { ContractError, isScopeId, isSettingKey, type ScopeId, type SettingKey } from '@hryvinskyi/datalayer-setup';

/** Where delivery stands at one scope, worded by the server. */
export interface StatusView {
    readonly pending: number;
    readonly sent: number;
    readonly failed: number;
    /** The age of the oldest waiting event and the last run. */
    readonly progress: string;
    /** The failed count with the latest error; empty while nothing failed. */
    readonly failedLine: string;
}

export interface StepData {
    readonly testUrl: string;
    readonly retryUrl: string;
    /** Whether the administrator may send a test event and queue failed events again. */
    readonly canAct: boolean;
    /** The transports a test event may go through. */
    readonly testableTransports: ReadonlySet<string>;
    /** The settings each transport shows, in order. */
    readonly transportFields: ReadonlyMap<string, readonly SettingKey[]>;
    /** The glyph name of each transport. */
    readonly transportIcons: ReadonlyMap<string, string>;
    /** Delivery status of the default scope and of each website. */
    readonly status: ReadonlyMap<ScopeId, StatusView>;
}

export interface RetryAnswer {
    readonly done: boolean;
    /** Translated by the server. */
    readonly message: string;
    readonly status: StatusView;
}

type Fields = Readonly<Record<string, unknown>>;

const isFields = (value: unknown): value is Fields =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

function record(value: unknown, path: string): Fields {
    if (!isFields(value)) {
        throw new ContractError(path, 'object');
    }
    return value;
}

function text(value: unknown, path: string): string {
    if (typeof value !== 'string') {
        throw new ContractError(path, 'string');
    }
    return value;
}

function bool(value: unknown, path: string): boolean {
    if (typeof value !== 'boolean') {
        throw new ContractError(path, 'boolean');
    }
    return value;
}

function count(value: unknown, path: string): number {
    if (typeof value !== 'number' || !Number.isInteger(value) || value < 0) {
        throw new ContractError(path, 'whole number');
    }
    return value;
}

function list<T>(value: unknown, path: string, item: (entry: unknown, path: string) => T): readonly T[] {
    if (!Array.isArray(value)) {
        throw new ContractError(path, 'array');
    }
    return value.map((entry: unknown, index: number) => item(entry, `${path}[${index}]`));
}

function settingKey(value: unknown, path: string): SettingKey {
    const raw = text(value, path);
    if (!isSettingKey(raw)) {
        throw new ContractError(path, 'setting key');
    }
    return raw;
}

function parseStatus(value: unknown, path: string): StatusView {
    const fields = record(value, path);
    return {
        pending: count(fields['pending'], `${path}.pending`),
        sent: count(fields['sent'], `${path}.sent`),
        failed: count(fields['failed'], `${path}.failed`),
        progress: text(fields['progress'], `${path}.progress`),
        failedLine: text(fields['failedLine'], `${path}.failedLine`),
    };
}

function statusByScope(value: unknown, path: string): ReadonlyMap<ScopeId, StatusView> {
    const status = new Map<ScopeId, StatusView>();
    for (const [scope, view] of Object.entries(record(value, path))) {
        if (!isScopeId(scope)) {
            throw new ContractError(`${path}.${scope}`, 'scope id key');
        }
        status.set(scope, parseStatus(view, `${path}.${scope}`));
    }
    return status;
}

/** The step's page data. */
export function parseStepData(value: unknown): StepData {
    const fields = record(value, '$');
    const fieldsByTransport = Object.entries(record(fields['transportFields'], '$.transportFields'))
        .map(([code, keys]): readonly [string, readonly SettingKey[]] =>
            [code, list(keys, `$.transportFields.${code}`, settingKey)]);
    const icons = Object.entries(record(fields['transportIcons'], '$.transportIcons'))
        .map(([code, icon]): readonly [string, string] => [code, text(icon, `$.transportIcons.${code}`)]);
    return {
        testUrl: text(fields['testUrl'], '$.testUrl'),
        retryUrl: text(fields['retryUrl'], '$.retryUrl'),
        canAct: bool(fields['canAct'], '$.canAct'),
        testableTransports: new Set(list(fields['testableTransports'], '$.testableTransports', text)),
        transportFields: new Map(fieldsByTransport),
        transportIcons: new Map(icons),
        status: statusByScope(fields['status'], '$.status'),
    };
}

/** What queueing failed events again answers. */
export function parseRetryAnswer(value: unknown): RetryAnswer {
    const fields = record(value, '$');
    return {
        done: bool(fields['done'], '$.done'),
        message: text(fields['message'], '$.message'),
        status: parseStatus(fields['status'], '$.status'),
    };
}
