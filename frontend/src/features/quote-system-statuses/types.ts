/**
 * Quote system statuses CRUD types (spec 0404 part A). Source of truth: the
 * frozen `data_contract` of the spec. A system status is the configurable,
 * global status Offerte shows; each working status of a quote workflow links
 * one, and its `behavior` is the key the code knows (contract on closed_won,
 * Gestione Iscritti on validated/closed_won, ...).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Behaviors a system status may carry (mirror of the backend `WorkflowStatusGroup`). */
export const QUOTE_SYSTEM_STATUS_BEHAVIORS = ['open', 'pending', 'validated', 'closed_won', 'closed_lost'] as const

export type QuoteSystemStatusBehavior = (typeof QUOTE_SYSTEM_STATUS_BEHAVIORS)[number]

/** Mandatory rows (not deletable, behavior fixed); `null` on an ordinary row. */
export type QuoteSystemStatusKey = 'open' | 'closed_won' | 'closed_lost' | null

/** Single system status as returned by GET/POST/PATCH /quote-system-statuses (envelope `data`). */
export interface QuoteSystemStatusDetail {
  id: number
  name: string
  color: string | null
  behavior: QuoteSystemStatusBehavior
  system_key: QuoteSystemStatusKey
  sort_order: number
  /** How many working statuses link this one: a linked status cannot be deleted. */
  workflow_statuses_count: number
  created_at: string
  updated_at: string
}

/** A detail carrying the actor's authorization metadata for this instance (spec 0004). */
export interface QuoteSystemStatusDetailWithPermissions extends QuoteSystemStatusDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /quote-system-statuses. */
export interface CreateQuoteSystemStatusPayload {
  name: string
  color: string
  behavior: QuoteSystemStatusBehavior
}

/** Payload for PATCH /quote-system-statuses/{id}: only what changed. */
export type UpdateQuoteSystemStatusPayload = Partial<CreateQuoteSystemStatusPayload>

export type QuoteSystemStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; quoteSystemStatus: QuoteSystemStatusDetailWithPermissions }
