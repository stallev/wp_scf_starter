/**
 * Global setup: probe the site under test and start the static server for the visual suite.
 *
 * Static server mounts (origin exported as E2E_STATIC_ORIGIN to the workers):
 *   /prototype/ → VISUAL_PROTOTYPE_DIR or project.config.json paths.prototype
 *   /fixture/   → fixtures/demo-prototype (visual self-check; works without WordPress)
 */
import { existsSync } from 'node:fs';
import path from 'node:path';
import { ROOT, baseURL, config } from './helpers/config';
import { startStaticServer } from './helpers/static-server';

export const FIXTURE_DIR = 'fixtures/demo-prototype';

export default async function globalSetup(): Promise<() => Promise<void>> {
  try {
    const res = await fetch(`${baseURL}/`, { redirect: 'manual', signal: AbortSignal.timeout(15_000) });
    if (res.status >= 500) console.warn(`[e2e] ${baseURL}/ answers HTTP ${res.status}`);
  } catch (err) {
    console.warn(`[e2e] ${baseURL} is not reachable (${(err as Error).message}). Start it: npm run env:start. Only the visual self-check can pass without it.`);
  }

  const prototypeDir = path.resolve(ROOT, process.env.VISUAL_PROTOTYPE_DIR || config.paths.prototype);
  const mounts: Record<string, string> = { '/fixture/': path.join(ROOT, FIXTURE_DIR) };
  if (existsSync(prototypeDir)) mounts['/prototype/'] = prototypeDir;

  const server = await startStaticServer(mounts);
  process.env.E2E_STATIC_ORIGIN = server.origin;
  return server.close;
}
