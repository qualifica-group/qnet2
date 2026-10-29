import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import type { GridApi } from 'ag-grid-community'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { bulkDeleteTableRows } from '@/features/table/api'
import type { TableRow } from '@/features/table/types'

interface UseBulkDeleteArgs {
  /** Domain key selecting the server-side table definition (e.g. "users"). */
  domain: string
  /** Grid API once ready (null while loading); used to clear the selection. */
  gridApi: GridApi<TableRow> | null
  /** Purges and reloads the SSRM cache after a successful delete. */
  refresh: () => void
}

interface UseBulkDeleteResult {
  /**
   * Confirms, deletes the given ids, and reports the outcome. Resolves `true`
   * only when the request actually ran (the caller can then drop its own
   * `selectedIds` state); `false` on cancel or on a failed request, so the
   * caller keeps the selection for a retry.
   */
  runBulkDelete: (ids: number[]) => Promise<boolean>
  isDeleting: boolean
}

/**
 * Generic bulk-delete flow shared by every table domain: confirm (destructive
 * tone) → `POST /tables/{domain}/bulk-delete` → summary toast (or a
 * partial-failure warning when the backend skipped some rows) → clear the
 * grid selection and purge-reload the SSRM cache. Domain-agnostic: it only
 * ever deals with the ids the caller passes in, never a row shape.
 */
export function useBulkDelete({
  domain,
  gridApi,
  refresh,
}: UseBulkDeleteArgs): UseBulkDeleteResult {
  const { t, i18n } = useTranslation()
  const confirm = useConfirm()
  const [isDeleting, setIsDeleting] = useState(false)

  const runBulkDelete = useCallback(
    async (ids: number[]): Promise<boolean> => {
      if (ids.length === 0) {
        return false
      }

      // Step 1: confirm the destructive, irreversible action.
      const confirmed = await confirm({
        tone: 'destructive',
        title: t('table.bulkDeleteConfirmTitle'),
        description: t('table.bulkDeleteConfirmBody', { count: ids.length }),
      })
      if (!confirmed) {
        return false
      }

      // Step 2: run the request and report the outcome.
      setIsDeleting(true)
      try {
        const result = await bulkDeleteTableRows(domain, ids)
        if (result.failed.length > 0) {
          toast.warning(
            t('table.bulkDeletePartial', {
              deleted: result.deleted,
              failed: result.failed.length,
            }),
          )
        } else {
          toast.success(t('table.bulkDeleted', { count: result.deleted }))
        }
        // Step 3: clear the grid selection and refresh the SSRM cache.
        gridApi?.deselectAll()
        refresh()
        return true
      } catch (error) {
        toast.error(apiErrorMessage(error, t('table.bulkDeleteError'), i18n))
        return false
      } finally {
        setIsDeleting(false)
      }
    },
    [domain, gridApi, refresh, confirm, t, i18n],
  )

  return { runBulkDelete, isDeleting }
}
