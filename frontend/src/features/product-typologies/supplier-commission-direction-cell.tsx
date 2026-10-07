import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'

export function SupplierCommissionDirectionCell({ value }: { value: unknown }) {
  const { t } = useTranslation()
  if (value !== 'RECEIVED' && value !== 'PAID') {
    return <span className="text-muted-foreground">—</span>
  }
  return (
    <div className="flex h-full items-center">
      <Badge variant="secondary">{t(`productTypologies.supplierCommissionDirection.${value}`)}</Badge>
    </div>
  )
}
