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
import { financialAccountColumnRenderers } from '@/features/financial-accounts/column-renderers'
import { deleteFinancialAccount } from '@/features/financial-accounts/api'

/** Domain key used to mount the generic table for financial accounts. */
const FINANCIAL_ACCOUNTS_DOMAIN = 'financial-accounts'

/** Backend message of a restrictive 409, when the response carries one. */
function conflictMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error)) {
    return null
  }
  const message = (error.response?.data as { message?: unknown } | undefined)?.message
  return typeof message === 'string' && message !== '' ? message : null
}

/**
 * Thin Financial Accounts adapter over the generic table: mounts `<TableView>`
 * with the `financial-accounts` domain and its renderers, delegates the open
 * mode of view/edit/create to `useModuleOpener`, and owns the delete flow (the
 * generic table asks for confirmation; a 409 for a bank account with linked
 * cards shows the backend message). Permission gating is an affordance only;
 * the backend re-authorizes each call.
 */
export function FinancialAccountsTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(FINANCIAL_ACCOUNTS_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteFinancialAccount(Number(row.id))
        toast.success(t('financialAccounts.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('financialAccounts.form.deleteForbidden'))
        } else if (status === 409) {
          toast.error(conflictMessage(error) ?? t('financialAccounts.form.deleteInUse'))
        } else {
          toast.error(t('financialAccounts.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
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
          <Can permission="financial-accounts.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('financialAccounts.form.newFinancialAccount')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={FINANCIAL_ACCOUNTS_DOMAIN}
        renderers={financialAccountColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={FINANCIAL_ACCOUNTS_DOMAIN}
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
