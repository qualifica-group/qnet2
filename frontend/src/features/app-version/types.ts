/**
 * The two deploy identifiers a running client probes, each null when its probe
 * failed or the check is not configured on that side. Null always degrades to
 * "no update detected": an unreachable probe must never nag the user.
 */
export interface DeployedVersions {
  frontend: string | null
  backend: string | null
}
