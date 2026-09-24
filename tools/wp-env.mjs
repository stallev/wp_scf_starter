/**
 * wp-env wrapper.
 *  - On `start`: resolves WordPress core and wordpress.org plugins from project.config.json
 *    ("latest" → latest stable from api.wordpress.org, never RC/trunk; or a pinned version)
 *    and writes them to .wp-env.override.json (gitignored), so both environments use them.
 *  - `run <service> …` goes straight to `docker exec` when the container is already running
 *    (wp-env run costs ~90 s per call on Windows; exec — under a second). Falls back to wp-env.
 *  - Preloads tools/dns-fallback.cjs (see there why).
 * Usage: node tools/wp-env.mjs <wp-env args>   (npm run env:start, npm run wp -- …)
 */
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { ROOT, loadConfig, run } from './lib.mjs';

const OVERRIDE = path.join(ROOT, '.wp-env.override.json');
const CORE_API = 'https://api.wordpress.org/core/stable-check/1.0/';
const PLUGIN_API = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[fields][sections]=0&request[slug]=';
const args = process.argv.slice(2);

function cmpVersion(a, b) {
  const pa = a.split('.').map(Number);
  const pb = b.split('.').map(Number);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const d = (pa[i] ?? 0) - (pb[i] ?? 0);
    if (d) return d;
  }
  return 0;
}

async function getJson(url) {
  const res = await fetch(url, { signal: AbortSignal.timeout(15000) });
  if (!res.ok) throw new Error(`HTTP ${res.status} ${url}`);
  return res.json();
}

async function resolveCore(version) {
  if (version !== 'latest') return version;
  const found = Object.entries(await getJson(CORE_API)).find(([, state]) => state === 'latest');
  if (!found) throw new Error('no "latest" WordPress entry');
  return found[0];
}

async function resolvePlugin(slug, version) {
  if (version !== 'latest') return `https://downloads.wordpress.org/plugin/${slug}.${version}.zip`;
  const info = await getJson(PLUGIN_API + encodeURIComponent(slug));
  if (!info.version) throw new Error(`no stable version for plugin ${slug}`);
  return `https://downloads.wordpress.org/plugin/${slug}.${info.version}.zip`;
}

function readOverride() {
  return existsSync(OVERRIDE) ? JSON.parse(readFileSync(OVERRIDE, 'utf8')) : {};
}

async function syncSources() {
  const { wordpress } = loadConfig();
  const override = readOverride();
  let next;

  try {
    const coreVersion = await resolveCore(wordpress.version);
    if (cmpVersion(coreVersion, wordpress.min) < 0) {
      console.error(`[wp-env] WordPress ${coreVersion} is below wordpress.min ${wordpress.min} (project.config.json).`);
      process.exit(2);
    }
    const plugins = await Promise.all(
      Object.entries(wordpress.plugins).map(([slug, version]) => resolvePlugin(slug, version)),
    );
    next = { ...override, core: `https://wordpress.org/wordpress-${coreVersion}.zip`, plugins };
  } catch (err) {
    if (override.core && override.plugins) {
      console.warn(`[wp-env] Cannot resolve versions (${err.message}); keeping cached .wp-env.override.json.`);
      return;
    }
    console.error(`[wp-env] Cannot resolve versions (${err.message}) and nothing cached. Pin versions in project.config.json.`);
    process.exit(2);
  }

  if (JSON.stringify(next) !== JSON.stringify(override)) {
    writeFileSync(OVERRIDE, `${JSON.stringify(next, null, 2)}\n`);
    console.log(`[wp-env] Sources → ${[next.core, ...next.plugins].map((u) => path.basename(u, '.zip')).join(', ')}`);
  }
}

/** Running container of a wp-env service that mounts this repo, or null. */
function findContainer(service) {
  const ps = spawnSync('docker', ['ps', '--filter', `label=com.docker.compose.service=${service}`, '--format', '{{.Names}}'], { encoding: 'utf8' });
  if (ps.status !== 0) return null;
  // Docker Desktop reports mounts as "C:\repo\…" or "/run/desktop/mnt/host/c/repo/…".
  // Normalize both to "/c/repo/…" and compare the full tail (drive included).
  const norm = (p) => p.replace(/\\/g, '/').replace(/^([a-z]):/i, '/$1').toLowerCase();
  const target = `${norm(ROOT)}/wp-content/mu-plugins`;
  for (const name of ps.stdout.split(/\r?\n/).filter(Boolean)) {
    const inspect = spawnSync('docker', ['inspect', name, '--format', '{{range .Mounts}}{{.Source}}|{{end}}'], { encoding: 'utf8' });
    const mounts = (inspect.stdout || '').split('|').map(norm);
    if (mounts.some((m) => m.endsWith(target))) return name;
  }
  return null;
}

if (args[0] === 'start') {
  await syncSources();
}

if (args[0] === 'run' && args[1] && !args[1].startsWith('-')) {
  const container = findContainer(args[1]);
  if (container) {
    const tty = process.stdin.isTTY && process.stdout.isTTY ? ['-it'] : ['-i'];
    const env = { ...process.env, MSYS_NO_PATHCONV: '1' };
    process.exit(run('docker', ['exec', ...tty, '-w', '/var/www/html', container, ...args.slice(2)], { env, shell: false }));
  }
}

const bin = path.join(ROOT, 'node_modules', '@wordpress', 'env', 'bin', 'wp-env');
const preload = `--require ${JSON.stringify(path.join(ROOT, 'tools', 'dns-fallback.cjs'))}`;
const env = { ...process.env, NODE_OPTIONS: [process.env.NODE_OPTIONS, preload].filter(Boolean).join(' ') };

process.exit(run(process.execPath, [bin, ...args], { env, shell: false }));
