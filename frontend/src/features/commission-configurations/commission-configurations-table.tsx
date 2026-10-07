import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { COMMISSION_CONFIGURATIONS_DOMAIN } from './api'
import { commissionConfigurationColumnRenderers } from './column-renderers'
import { useCommissionConfigurationRowActions } from './use-commission-configuration-row-actions'

export function CommissionConfigurationsTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refresh = useCallback(() => tableRef.current?.refresh(), [])
  const { handleAction, isBusy, openCreate, sheet, dialogs } = useCommissionConfigurationRowActions({ onMutated: refresh })
  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader actions={<Can permission="commission-configurations.create"><Button onClick={openCreate}><Plus aria-hidden="true" />{t('commissionConfigurations.form.new')}</Button></Can>} />
      <TableView ref={tableRef} domain={COMMISSION_CONFIGURATIONS_DOMAIN} renderers={commissionConfigurationColumnRenderers} onAction={handleAction} isBusy={isBusy} />
      {sheet}
      {dialogs}
    </div>
  )
}
