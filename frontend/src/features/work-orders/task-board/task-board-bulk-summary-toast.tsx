/**
 * AC-028's post-submit summary: one toast reporting `succeeded`/`failed`,
 * with the per-task reason listed when at least one row failed — the server
 * already returns `results[].message` as human text (D-7's own contract
 * comment: "il motivo i18n del fallimento"), so this renders it as-is rather
 * than re-keying it through i18n.
 */

import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import type { BulkTaskResult } from '@/features/work-orders/task-board/types'

export function showBulkResultToast(t: TFunction, result: BulkTaskResult): void {
  const summary = t('workOrders.taskBoard.bulk.summary', { succeeded: result.succeeded, failed: result.failed })

  if (result.failed === 0) {
    toast.success(summary)
    return
  }

  const reasons = result.results.filter((row): row is { task_id: number; ok: false; message: string } => !row.ok && row.message !== null)

  toast.error(summary, {
    description: (
      <ul className="mt-1 list-disc space-y-0.5 pl-4 text-xs">
        {reasons.map((row) => (
          <li key={row.task_id}>{row.message}</li>
        ))}
      </ul>
    ),
  })
}
