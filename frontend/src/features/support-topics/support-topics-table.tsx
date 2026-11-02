import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { ApiErrorResponse } from '@/api/types'
import { supportTopicColumnRenderers } from '@/features/support-topics/column-renderers'
import { deleteSupportTopic } from '@/features/support-topics/api'
import { forbiddenMessage } from '@/lib/forbidden-message'

/** Domain key used to mount the generic table for support topics. */
const SUPPORT_TOPICS_DOMAIN = 'support-topics'

/**
 * Thin adapter over the generic table (spec 0334): mounts `<TableView>` on the `support-topics`
 * domain, delegates view/create to `useModuleOpener`, owns the delete flow and refreshes the grid
 * after every mutation. Permission gating is an affordance only; the backend re-authorizes.
 */
export function SupportTopicsTable() {
  const { t, i18n } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(SUPPORT_TOPICS_DOMAIN, { onSaved: refreshGrid })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteSupportTopic(Number(row.id))
        toast.success(t('supportTopics.form.deleted'))
        refreshGrid()
      } catch (error) {
        const forbidden = axios.isAxiosError<ApiErrorResponse>(error) ? forbiddenMessage(error, i18n) : null
        toast.error(forbidden ?? t('supportTopics.form.deleteError'))
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, i18n],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        default:
          break
      }
    },
    [openView, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="support-topics.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('supportTopics.form.newSupportTopic')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={SUPPORT_TOPICS_DOMAIN}
        renderers={supportTopicColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={SUPPORT_TOPICS_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}
