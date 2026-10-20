/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Proves the committed browser files are exactly what the sources build to: rebuilds into a temporary directory and
 * compares every file byte for byte with view/adminhtml/web/js/setup/. A file that differs, is missing, or is
 * committed without the build producing it fails the check (exit code 1). That directory belongs to the build
 * alone.
 */
import { execFileSync } from 'node:child_process';
import { mkdtemp, readdir, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const COMMITTED = resolve(ROOT, '../../view/adminhtml/web/js/setup');

/**
 * Every file under a directory, as paths relative to it; none when the directory does not exist.
 *
 * @param {string} dir
 * @returns {Promise<string[]>}
 */
async function filesIn(dir) {
    try {
        const entries = await readdir(dir, { recursive: true, withFileTypes: true });
        return entries
            .filter((entry) => entry.isFile())
            .map((entry) => relative(dir, join(entry.parentPath, entry.name)))
            .sort();
    } catch (error) {
        if (error instanceof Error && 'code' in error && error.code === 'ENOENT') {
            return [];
        }
        throw error;
    }
}

const scratch = await mkdtemp(join(tmpdir(), 'datalayer-setup-server-build-'));
const problems = [];
try {
    execFileSync(process.execPath, [join(ROOT, 'scripts/build.mjs'), '--out', scratch], { stdio: 'inherit' });
    const built = await filesIn(scratch);
    const committed = new Set(await filesIn(COMMITTED));
    for (const file of built) {
        if (!committed.has(file)) {
            problems.push(`missing: ${file} is built but not committed`);
            continue;
        }
        const [fresh, kept] = await Promise.all([readFile(join(scratch, file)), readFile(join(COMMITTED, file))]);
        if (!fresh.equals(kept)) {
            problems.push(`differs: ${file} does not match what its sources build to`);
        }
        committed.delete(file);
    }
    for (const file of committed) {
        problems.push(`stale: ${file} is committed but the build no longer produces it`);
    }
} finally {
    await rm(scratch, { recursive: true, force: true });
}

if (problems.length > 0) {
    console.error(`The committed build in ${COMMITTED} is out of date. Run npm run build.\n  ${problems.join('\n  ')}`);
    process.exit(1);
}
console.log(`The committed build in ${COMMITTED} matches its sources.`);
