import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Percent } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { RecordCard } from '@/components/detail/record-panel'
import { useAbilities } from '@/features/auth/use-abilities'
import { COMMISSION_CONFIGURATIONS_DOMAIN } from '@/features/commission-configurations/api'
import { commissionConfigurationColumnRenderers } from '@/features/commission-configurations/column-renderers'
import { COMMISSION_CREATE_REFERENT_PARAM } from '@/features/commission-configurations/types'
import { useCommissionConfigurationRowActions } from '@/features/commission-configurations/use-commission-configuration-row-actions'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { RegistryRelatedGrid } from '@/features/registries/registry-related-grid'
import type { TableViewHandle } from '@/features/table/table-view'

const COMMISSIONS_TAB = 'commission-configurations'

interface ReferentRelatedRecordsProps {
  referentId: number
}

/**
 * The referente's related records (spec 0336), full width below the record
 * like the anagrafica's (spec 0199/0204): the Configuratore commissioni rules
 * whose recipient is this referente, on the module's own grid, row actions
 * and create, the create opened as a personal REPORTER rule on it. Absent
 * without `commission-configurations.viewAny`.
 */
export function ReferentRelatedRecords({ referentId }: ReferentRelatedRecordsProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [count, setCount] = useState<number | null>(null)
  const tableRef = useRef<TableViewHandle>(null)
  const refresh = useCallback(() => tableRef.current?.refresh(), [])
  const handleRowCount = useCallback((next: number | null) => {
    if (next !== null) {
      setCount(next)
    }
  }, [])
  const { handleAction, isBusy, openCreateWith, sheet, dialogs } = useCommissionConfigurationRowActions({
    onMutated: refresh,
    forceMode: OPEN_MODE_MODAL,
  })

  if (!can('commission-configurations.viewAny')) {
    return null
  }

  return (
    <RecordCard>
      <Tabs defaultValue={COMMISSIONS_TAB} className="gap-0">
        <div className="overflow-x-auto px-4 py-3">
          <TabsList aria-label={t('referents.detail.related.title')}>
            <TabsTrigger value={COMMISSIONS_TAB}>
              <Percent className="size-3.5" aria-hidden="true" />
              {t('referents.detail.related.commissionConfigurations')}
              {count !== null ? (
                <Badge
                  variant="secondary"
                  className="px-1.5 py-0 text-[10px]"
                  aria-label={t('referents.detail.related.countLabel', { count })}
                >
                  {count}
                </Badge>
              ) : null}
            </TabsTrigger>
          </TabsList>
        </div>
        <div className="border-t" />
        <div className="min-w-0 p-4">
          <TabsContent value={COMMISSIONS_TAB}>
            <RegistryRelatedGrid
              ref={tableRef}
              domain={COMMISSION_CONFIGURATIONS_DOMAIN}
              rowScope={{ referentId }}
              renderers={commissionConfigurationColumnRenderers}
              onAction={handleAction}
              isBusy={isBusy}
              onRowCountChanged={handleRowCount}
              create={
                can('commission-configurations.create')
                  ? {
                      label: t('commissionConfigurations.form.new'),
                      onCreate: () => openCreateWith({ [COMMISSION_CREATE_REFERENT_PARAM]: referentId }),
                    }
                  : null
              }
            />
          </TabsContent>
        </div>
      </Tabs>
      {sheet}
      {dialogs}
    </RecordCard>
  )
}
