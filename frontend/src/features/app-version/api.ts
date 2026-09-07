import axios from 'axios'
import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { DeployedVersions } from '@/features/app-version/types'

/** Manifest emitted next to index.html by the build (vite/build-version.ts). */
const VERSION_MANIFEST_PATH = '/version.json'

/**
 * Reads the `version` string out of an unknown payload. The manifest is fetched
 * from the SPA origin, where an over-eager history fallback can answer with
 * `index.html` instead of a 404, so the shape is validated rather than trusted.
 */
function readVersion(payload: unknown): string | null {
  if (typeof payload !== 'object' || payload === null) {
    return null
  }
  const version = (payload as { version?: unknown }).version
  return typeof version === 'string' && version !== '' ? version : null
}

/**
 * Deployed FRONTEND build, read from the static manifest on the SPA's own
 * origin — deliberately NOT through `apiClient`, whose baseURL points at the
 * API host: this file ships with the bundle, not with the API. Cache busting is
 * belt and braces (a query param the CDN cannot collapse, plus `no-store`):
 * a cached manifest would freeze the client on its own version forever.
 */
async function fetchFrontendVersion(): Promise<string | null> {
  const { data } = await axios.get<unknown>(VERSION_MANIFEST_PATH, {
    params: { t: Date.now() },
    headers: { 'Cache-Control': 'no-store' },
  })
  return readVersion(data)
}

/** Deployed BACKEND build, from the public probe GET /api/version. */
async function fetchBackendVersion(): Promise<string | null> {
  const { data } = await apiClient.get<ApiResponse<{ version: string | null }>>('/version')
  return readVersion(data.data)
}

/**
 * Probes both deployed versions in one tick.
 *
 * Each probe swallows its own failure and resolves to null BY DESIGN: this is
 * advisory background polling, so a flaky network, an API restart or a missing
 * manifest must degrade to "no update detected" instead of surfacing an error
 * or taking down the half that still works. The next tick retries, and a
 * permanently failing probe simply never prompts.
 */
export async function fetchDeployedVersions(): Promise<DeployedVersions> {
  const [frontend, backend] = await Promise.all([
    fetchFrontendVersion().catch(() => null),
    fetchBackendVersion().catch(() => null),
  ])

  return { frontend, backend }
}
