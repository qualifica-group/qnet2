import { execSync } from 'node:child_process'
import type { Plugin } from 'vite'

/**
 * Name of the manifest emitted next to `index.html`. The running client polls
 * it (same origin as the SPA, so it works whether `dist/` is served by nginx or
 * copied into Laravel's `public/`) to learn which build is currently deployed.
 */
export const VERSION_MANIFEST_FILE = 'version.json'

/**
 * Identifier of the build being produced. The commit SHA is the honest answer —
 * rebuilding the same commit yields the same id, so a redeploy of unchanged
 * code does not nag anyone to reload. Outside a git checkout (a CI tarball, a
 * container build without `.git`) it falls back to the build timestamp, which
 * errs the safe way: every build looks new.
 */
export function resolveBuildVersion(): string {
  try {
    return execSync('git rev-parse --short HEAD', { stdio: ['ignore', 'pipe', 'ignore'] })
      .toString()
      .trim()
  } catch {
    return Date.now().toString(36)
  }
}

/**
 * Emits `dist/version.json` carrying the build identifier. Build-only: in dev
 * there is no deployed build to compare against and the check stays off.
 */
export function buildVersionPlugin(version: string): Plugin {
  return {
    name: 'build-version',
    apply: 'build',
    generateBundle() {
      this.emitFile({
        type: 'asset',
        fileName: VERSION_MANIFEST_FILE,
        source: JSON.stringify({ version }),
      })
    },
  }
}
