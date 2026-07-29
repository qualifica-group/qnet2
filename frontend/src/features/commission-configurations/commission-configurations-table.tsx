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
import { deleteCommissionConfiguration } from './api'
import { commissionConfigurationColumnRenderers } from './column-renderers'

export function CommissionConfigurationsTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const refresh = useCallback(() => tableRef.current?.refresh(), [])
  const { openCreate, openView, openEdit, sheet } = useModuleOpener('commission-configurations', { onSaved: refresh })
  const remove = useCallback(async (row: TableRow) => {
    setDeletingId(row.id)
    try {
      await deleteCommissionConfiguration(row.id)
      toast.success(t('commissionConfigurations.form.deleted'))
      refresh()
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 409) {
        toast.error(error.response.data?.message ?? t('commissionConfigurations.form.deleteReferenced'))
      } else {
        toast.error(t('commissionConfigurations.form.deleteError'))
      }
    } finally {
      setDeletingId(null)
    }
  }, [refresh, t])
  const handleAction: RowActionHandler = useCallback((action: TableActionDefinition, row: TableRow) => {
    if (action.key === 'view') openView(row)
    if (action.key === 'edit') openEdit(row)
    if (action.key === 'delete') void remove(row)
    if (action.key === 'activity') setActivityRow(row)
  }, [openEdit, openView, remove])
  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader actions={<Can permission="commission-configurations.create"><Button onClick={openCreate}><Plus aria-hidden="true" />{t('commissionConfigurations.form.new')}</Button></Can>} />
      <TableView ref={tableRef} domain="commission-configurations" renderers={commissionConfigurationColumnRenderers} onAction={handleAction} isBusy={(row) => row.id === deletingId} />
      {sheet}
      <ResourceActivityDialog resource="commission-configurations" row={activityRow} onOpenChange={(open) => { if (!open) setActivityRow(null) }} />
    </div>
  )
}
