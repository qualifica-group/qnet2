import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CostTypesTable } from '@/features/cost-types/cost-types-table'

/**
 * Cost types page. Light composition only: gates access with
 * `cost-types.view` and mounts the thin adapter over the generic table
 * (`domain="cost-types"`).
 */
export default function CostTypesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="cost-types.view"
      fallback={<p className="text-sm text-muted-foreground">{t('costTypes.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <CostTypesTable />
      </div>
    </Can>
  )
}
