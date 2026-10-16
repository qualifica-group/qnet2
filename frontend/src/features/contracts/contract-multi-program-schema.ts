import axios from 'axios'
import { blankToNull } from '@/lib/utils'
import {
  contractProgramDefaultValues,
  type ContractProgramFormValues,
} from '@/features/contracts/contract-program-schema'
import type { MultiProgrammableLine, MultiQuoteWorkOrderPayload } from '@/features/contracts/types'

/** Upper bound of contracts in one selection (spec 0267: `contract_ids` max 50). */
export const MULTI_PROGRAM_MAX_CONTRACTS = 50

/** The single group of the multi-offer form: the dialog creates exactly ONE work order. */
export const MULTI_GROUP_PREFIX = 'groups.0' as const

const MULTI_FIELDS = ['title', 'type', 'start_date', 'supervisor_ids', 'task_template_id', 'quote_line_ids'] as const

/**
 * The multi-offer dialog reuses the single-contract schema and shared fields
 * (`buildContractProgramSchema`): the form holds one group, whose fields are
 * exactly the ones the endpoint takes, so the rules cannot drift.
 */
export function multiProgramDefaultValues(): ContractProgramFormValues {
  const base = contractProgramDefaultValues()
  return {
    ...base,
    groups: [{ key: 'multi', title: '', ...base.common, quote_line_ids: [] }],
  }
}

/** `groups.0.{field}` form paths a 422 can land on. */
export const MULTI_ERROR_FIELDS = MULTI_FIELDS.map((field) => `${MULTI_GROUP_PREFIX}.${field}` as const)

/** Maps a server error key (`quote_line_ids`) onto its form path (`groups.0.quote_line_ids`). */
export function toMultiErrorPath(key: string) {
  return `${MULTI_GROUP_PREFIX}.${key}` as (typeof MULTI_ERROR_FIELDS)[number]
}

export function toMultiPayload(values: ContractProgramFormValues, contractIds: number[]): MultiQuoteWorkOrderPayload {
  const group = values.groups[0]
  return {
    contract_ids: contractIds,
    quote_line_ids: group.quote_line_ids,
    title: blankToNull(group.title),
    type: group.type,
    start_date: group.start_date,
    supervisor_ids: group.supervisor_ids,
    task_template_id: group.task_template_id,
  }
}

/** Ids a line may be ticked from: eligible, and in the branch of the first pick once there is one. */
export function selectableLineIds(lines: MultiProgrammableLine[], selected: number[]): Set<number> {
  const anchor = lines.find((line) => line.id === selected[0])
  return new Set(
    lines
      .filter((line) => line.multi_quote_eligible)
      .filter((line) => !anchor || line.branch_root?.id === anchor.branch_root?.id)
      .map((line) => line.id),
  )
}

/** Distinct offers covered by the picked lines: the endpoint needs at least 2. */
export function distinctQuoteCount(lines: MultiProgrammableLine[], selected: number[]): number {
  return new Set(lines.filter((line) => selected.includes(line.id)).map((line) => line.quote_id)).size
}

/**
 * The server's explanation when it refuses the selected contracts (spec 0267,
 * D-14: a 422 on `contract_ids` naming the contracts that cannot be grouped),
 * or undefined for any other failure.
 */
export function multiProgramRefusal(error: unknown): string | undefined {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return undefined
  }
  const data = error.response.data as { message?: string; errors?: Record<string, string[]> } | undefined
  return data?.errors?.contract_ids?.[0] ?? data?.message
}
