/**
 * System Health (spec 0187): live status of critical subsystems plus the
 * people currently online, shown on `/admin/system-health` (super-admin only).
 * Mirrors `GET /api/system-health` exactly (frozen data_contract).
 */

export type HealthStatus = 'ok' | 'degraded' | 'down'

export type HealthCheckKey = 'database' | 'email' | 'queue' | 'security'

export interface HealthDetail {
  key: string
  status: HealthStatus
  value: string | null
  message: string | null
}

export interface HealthCheck {
  key: HealthCheckKey
  status: HealthStatus
  latency_ms: number | null
  message: string | null
  details: HealthDetail[]
}

export interface OnlineUser {
  id: number
  name: string
  email: string
  last_seen_at: string
  impersonating: { id: number; name: string } | null
}

export interface OnlineUsers {
  count: number
  window_minutes: number
  users: OnlineUser[]
}

export interface SystemHealthData {
  overall: HealthStatus
  checked_at: string
  online: OnlineUsers
  checks: HealthCheck[]
}
