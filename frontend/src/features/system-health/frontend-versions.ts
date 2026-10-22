import { version as reactVersion } from 'react'
import { version as typescriptVersion } from 'typescript/package.json'
import type { RuntimeVersion } from '@/features/system-health/types'

/**
 * Frontend toolchain versions, fixed at build time: they describe the bundle
 * the browser is running, not the server (spec 0187 rev. 2026-10-22).
 */
export const FRONTEND_VERSIONS: RuntimeVersion[] = [
  { key: 'typescript', value: typescriptVersion },
  { key: 'react', value: reactVersion },
]
