/**
 * State of the bulk "Ripartizione" section (spec 0338 D-2/D-3/D-4). The quotas
 * are DERIVED: an even split of the total, unless the user edited them for the
 * very same (total, start time, tasks) — a change of any of those drops the
 * manual edits by construction (no effect needed). Reads the total and the
 * start time off the segnatempo form the dialog already owns.
 */
import { useState } from 'react'
import { useWatch } from 'react-hook-form'
import axios from 'axios'
import {
  shareSlots,
  splitMinutesEvenly,
  sumShares,
  validateShares,
  type ShareError,
  type ShareSlot,
  type TimeEntryShare,
} from '@/features/tasks/time-entry-shares'
import type { useTaskCompleteTimeEntryForm } from '@/features/tasks/use-task-complete-time-entry-form'

export interface TaskShareTarget {
  id: number
  title: string
}

interface Edits {
  key: string
  shares: TimeEntryShare[]
}

/** The first `time_entry_shares` message of a 422, if the server flagged that field. */
export function timeEntrySharesServerError(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  const key = Object.keys(errors ?? {}).find((field) => field === 'time_entry_shares' || field.startsWith('time_entry_shares.'))
  return key ? (errors?.[key]?.[0] ?? null) : null
}

export function useTaskBulkTimeEntryShares(
  timeEntryForm: ReturnType<typeof useTaskCompleteTimeEntryForm>,
  tasks: TaskShareTarget[],
) {
  const control = timeEntryForm.form.control
  const total = useWatch({ control, name: 'minutes' })
  const startTime = useWatch({ control, name: 'start_time' })
  const [edits, setEdits] = useState<Edits | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)

  const taskIds = tasks.map((task) => task.id)
  const key = `${total ?? ''}|${startTime ?? ''}|${taskIds.join(',')}`
  const hasTotal = typeof total === 'number' && total > 0
  const even = splitMinutesEvenly(hasTotal ? total : 0, taskIds).shares
  const shares = edits !== null && edits.key === key ? edits.shares : even

  const setMinutes = (taskId: number, raw: string) => {
    const parsed = Number.parseInt(raw, 10)
    const minutes = Number.isNaN(parsed) ? 0 : Math.max(0, parsed)
    setEdits({ key, shares: shares.map((share) => (share.task_id === taskId ? { ...share, minutes } : share)) })
    setServerError(null)
  }

  const equalSplit = () => {
    setEdits(null)
    setServerError(null)
  }

  const error: ShareError | null = hasTotal ? validateShares(total, shares) : null
  const slots: ShareSlot[] | null = hasTotal ? shareSlots(startTime, shares) : null

  return {
    shares,
    slots,
    total: hasTotal ? total : null,
    assigned: sumShares(shares),
    error,
    serverError,
    setMinutes,
    equalSplit,
    /** Wires a 422 on `time_entry_shares` into the section; `true` when it was one. */
    applyServerError: (cause: unknown): boolean => {
      const message = timeEntrySharesServerError(cause)
      setServerError(message)
      return message !== null
    },
    /** `false` blocks the submit: the total is unusable or the quotas break the rules. */
    isValid: hasTotal && error === null,
  }
}
