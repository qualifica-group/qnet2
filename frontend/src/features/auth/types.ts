import type { PersonalDataPayload } from '@/features/personal-data/drafts'
import type { PersonalDataCard } from '@/features/personal-data/types'
import type { ModuleOpenPreferences } from '@/features/modules/types'
import type { DateFormat, TimeFormat } from '@/lib/formatting/date-display'

export interface User {
  id: number
  /** Display name derived server-side from the personal-data card (read-only). */
  name: string
  email: string
  /** Preferred UI language (e.g. "en", "it"), sourced from the backend. */
  locale: string
  /**
   * Role memberships as {id, name} (UserResource shape). Role-based checks go
   * through the abilities map (see AbilityMap.roles), not this field.
   */
  roles: { id: number; name: string }[]
  /** Absolute URL to the authenticated avatar download endpoint, or null. */
  avatar_url: string | null
  /**
   * The user's personal-data card (registry + contacts + addresses), or null
   * when none has been created yet. Same shape as the Users module (ADR 0013).
   */
  personal_data?: PersonalDataCard | null
  /**
   * Employment slice of the connected actor (spec 0015 `employment`), narrowed
   * to what the app reads from it: the Sede operativa the request-management
   * create form defaults to (user directive 2026-08-04). Absent when the actor
   * has no employment profile — the full profile stays the Users module's own
   * `EmploymentDetail`.
   */
  employment?: { operational_site_id: number | null } | null
  created_at: string | null
  /** Per-user modal-vs-page open mode preference (spec 0042). Never null on the wire. */
  module_open_preferences: ModuleOpenPreferences
  /** Per-user UI scale slider (0..100). Never null on the wire (defaults to 40). */
  ui_scale: number
  /** Date pattern every screen renders with. Never null on the wire (defaults to 'dmy'). */
  date_format: DateFormat
  /** Clock convention for the time next to a date. Never null on the wire (defaults to '24h'). */
  time_format: TimeFormat
}

export interface LoginPayload {
  email: string
  password: string
}

export interface UpdateProfilePayload {
  /** Optional: PATCH /auth/me is a partial update (backend `sometimes`). */
  locale?: string
  /** Nested registry card + contacts + addresses (ADR 0013). */
  personal_data?: PersonalDataPayload
  /** Optional: updates the modal-vs-page open mode preference (spec 0042). */
  module_open_preferences?: ModuleOpenPreferences
  /** Optional: updates the per-user UI scale slider (0..100). */
  ui_scale?: number
  /** Optional: updates the date pattern every screen renders with. */
  date_format?: DateFormat
  /** Optional: updates the clock convention for times. */
  time_format?: TimeFormat
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export interface ForgotPasswordPayload {
  email: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface LoginResult {
  token: string
  token_type: string
  user: User
}

/** The original actor's identity while a token of impersonation is active. */
export interface Impersonator {
  id: number
  name: string
  email: string
}

/** GET /auth/impersonation response payload. */
export interface ImpersonationState {
  impersonator: Impersonator | null
}

/**
 * Ability map returned by GET /auth/me/abilities.
 * `permissions` maps every defined permission name to whether the user has it.
 */
export interface AbilityMap {
  roles: string[]
  permissions: Record<string, boolean>
}
