import axios from 'axios'
import type { Path } from 'react-hook-form'
import type { ContractProgramFormValues } from '@/features/contracts/contract-program-schema'

type GroupField = 'title' | 'type' | 'start_date' | 'supervisor_ids' | 'task_template_id' | 'quote_line_ids'

const GROUP_FIELDS: GroupField[] = ['title', 'type', 'start_date', 'supervisor_ids', 'task_template_id', 'quote_line_ids']

const GROUP_ERROR_KEY = /^groups\.(\d+)\./

/** Every `groups.{i}.{field}` form path of a batch with `count` groups, for `applyServerValidationErrors`. */
export function groupErrorPaths(count: number): Path<ContractProgramFormValues>[] {
  return Array.from({ length: count }, (_, index) =>
    GROUP_FIELDS.map((field): Path<ContractProgramFormValues> => `groups.${index}.${field}`),
  ).flat()
}

/** Indices of the groups a 422 points at (`groups.{i}.*` keys), sorted and unique. */
export function failingGroupIndices(error: unknown): number[] {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return []
  }
  const keys = Object.keys((error.response.data?.errors as Record<string, string[]> | undefined) ?? {})
  const indices = keys.flatMap((key) => {
    const match = GROUP_ERROR_KEY.exec(key)
    return match ? [Number(match[1])] : []
  })
  return [...new Set(indices)].sort((a, b) => a - b)
}
