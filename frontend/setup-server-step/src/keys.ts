/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The names this plugin shares with its server side: its step id, its plugin id (which keys its page data) and the
 * keys of the settings it registers there.
 */
import { requireSettingKey, requireStepId } from '@hryvinskyi/datalayer-setup';

export const SERVER_STEP = requireStepId('server');
export const PLUGIN_ID = 'server';

export const SS_ENABLED = requireSettingKey('ss_enabled');
export const TRANSPORT = requireSettingKey('transport');
export const MP_ID = requireSettingKey('mp_id');
export const API_SECRET = requireSettingKey('api_secret');
export const REGION = requireSettingKey('region');
export const ENDPOINT = requireSettingKey('endpoint');
export const SGTM_SECRET = requireSettingKey('sgtm_secret');
export const USER_ID = requireSettingKey('user_id');
export const FIRST_PARTY = requireSettingKey('first_party');
export const VISITOR_DAYS = requireSettingKey('visitor_days');
export const PURCHASE_TRIGGER = requireSettingKey('purchase_trigger');
export const DEBUG = requireSettingKey('debug');
export const MAX_ATTEMPTS = requireSettingKey('max_attempts');
