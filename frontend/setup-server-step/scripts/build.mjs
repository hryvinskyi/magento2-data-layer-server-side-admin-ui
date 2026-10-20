/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Builds the server-side step plugin into the module's view directory (or `--out <dir>`) as `server-step.js`.
 *
 * Preact and the setup wizard's SDK are external: the page's import map resolves them to the one copy the wizard
 * publishes, so the plugin's hooks read the very contexts the wizard provides. Externals match whole specifiers
 * only (esbuild's own `external` option would also swallow every subpath). The SDK's source is reachable through
 * the TypeScript `paths` for type checking; the build fails when any file outside this package's `src/` ends up in
 * the bundle, since that would be a second copy of the SDK, or when the output imports anything but a bare
 * specifier. The output is deterministic, so verify-build can compare it byte for byte.
 */
import { build } from 'esbuild';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const DEFAULT_OUT = resolve(ROOT, '../../view/adminhtml/web/js/setup');
const FILE = 'server-step.js';
const SHARED = ['preact', 'preact/hooks', 'preact/jsx-runtime', '@hryvinskyi/datalayer-setup'];

/**
 * Keeps exactly the listed specifiers out of the bundle.
 *
 * @param {string[]} specifiers
 * @returns {import('esbuild').Plugin}
 */
const exactExternals = (specifiers) => ({
    name: 'exact-externals',
    setup(builder) {
        builder.onResolve({ filter: /.*/ }, (args) => (specifiers.includes(args.path) ? { path: args.path, external: true } : undefined));
    },
});

const isBare = (specifier) => !specifier.startsWith('.') && !specifier.startsWith('/') && !/^[a-z]+:/i.test(specifier);

const { values } = parseArgs({ options: { out: { type: 'string' } } });
const outDir = resolve(values.out ?? DEFAULT_OUT);

const result = await build({
    entryPoints: ['src/index.ts'],
    absWorkingDir: ROOT,
    tsconfig: join(ROOT, 'tsconfig.json'),
    outfile: join(outDir, FILE),
    plugins: [exactExternals(SHARED)],
    bundle: true,
    format: 'esm',
    platform: 'browser',
    target: 'es2022',
    minify: true,
    splitting: false,
    legalComments: 'eof',
    charset: 'utf8',
    metafile: true,
    write: false,
    logLevel: 'warning',
});

const foreign = Object.keys(result.metafile.inputs).filter((input) => !input.startsWith('src/'));
if (foreign.length > 0) {
    throw new Error(`${FILE} bundles ${foreign.join(', ')}; import shared code through @hryvinskyi/datalayer-setup`);
}
for (const meta of Object.values(result.metafile.outputs)) {
    const relative = meta.imports.filter((item) => !item.external || !isBare(item.path));
    if (relative.length > 0) {
        throw new Error(`${FILE} imports ${relative.map((item) => item.path).join(', ')}; only bare specifiers are allowed`);
    }
}
const [file] = result.outputFiles;
if (file === undefined || result.outputFiles.length !== 1) {
    throw new Error(`${FILE}: expected exactly one output file`);
}
await mkdir(outDir, { recursive: true });
await writeFile(join(outDir, FILE), file.contents);
console.log(`Built ${FILE} into ${outDir}`);
