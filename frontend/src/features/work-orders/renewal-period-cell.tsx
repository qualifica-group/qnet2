import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'

/** `renewal_number` ("Periodo", spec 0358 D-14): "Iniziale" for 0, "Rinnovo N" otherwise. */
export function RenewalPeriodCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const number = typeof value === 'number' ? value : 0

  return <span>{number === 0 ? t('workOrders.period.initial') : t('workOrders.period.renewal', { number })}</span>
}
