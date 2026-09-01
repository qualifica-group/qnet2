import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { UnitsOfMeasureTable } from '@/features/units-of-measure/units-of-measure-table'

/**
 * Units of measure page. Light composition only: gates access with
 * `units-of-measure.viewAny` and mounts the thin units-of-measure adapter,
 * which in turn mounts the generic table (`domain="units-of-measure"`). The
 * generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here (mirrors `VatRatesPage`).
 */
export default function UnitsOfMeasurePage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="units-of-measure.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('unitsOfMeasure.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <UnitsOfMeasureTable />
      </div>
    </Can>
  )
}
