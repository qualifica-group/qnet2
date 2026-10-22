import { useState } from 'react'
import { useAbsenceSummary } from '@/features/absences/summary/use-absence-summary'
import type { AbsenceScope } from '@/features/absences/types'

/**
 * Active tab of the requests grid. It opens on "To approve" when the first
 * summary says there is something to decide (spec 0304 D-18), but only once: a
 * later refetch never moves the tab under the user, and a tab picked before the
 * summary arrives wins.
 */
export function useAbsenceScope() {
  const { data } = useAbsenceSummary()
  const [scope, setScope] = useState<AbsenceScope>('mine')
  const [settled, setSettled] = useState(false)

  if (!settled && data) {
    setSettled(true)
    if (data.to_approve_count > 0) setScope('to_approve')
  }

  const selectScope = (next: AbsenceScope) => {
    setSettled(true)
    setScope(next)
  }

  return { scope, selectScope, toApproveCount: data?.to_approve_count ?? 0 }
}
