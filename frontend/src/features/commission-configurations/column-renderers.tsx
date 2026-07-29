/* eslint-disable react-refresh/only-export-components -- renderer registry exports render functions and formatting helpers by design */
import i18n from '@/i18n'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { Badge } from '@/components/ui/badge'

function EnumCell({
  field,
  value,
  status = false,
}: {
  field: string
  value: unknown
  status?: boolean
}) {
  if (typeof value !== 'string' || value === '') {
    return <span className="text-muted-foreground">–</span>
  }
  const label = i18n.t(`commissionConfigurations.options.${field}.${value}`)
  return status ? (
    <Badge variant={value === 'ACTIVE' ? 'default' : 'secondary'}>{label}</Badge>
  ) : (
    <span>{label}</span>
  )
}

export function formatCommissionValue(value: unknown, type: unknown): string {
  const number = Number(value)
  if (!Number.isFinite(number)) return ''
  const formatted = new Intl.NumberFormat(i18n.language, {
    minimumFractionDigits: type === 'FIXED_AMOUNT' ? 2 : 0,
    maximumFractionDigits: 4,
  }).format(number)
  return type === 'PERCENTAGE' ? `${formatted}%` : formatted
}

export const commissionConfigurationColumnRenderers: TableRendererMap = {
  recipient_role: ({ value }) => <EnumCell field="recipient_role" value={value} />,
  application_scope: ({ value }) => <EnumCell field="application_scope" value={value} />,
  commission_type: ({ value }) => <EnumCell field="commission_type" value={value} />,
  status: ({ value }) => <EnumCell field="status" value={value} status />,
  value: ({ value, data }) => (
    <span className="block text-right tabular-nums">
      {formatCommissionValue(value, data?.commission_type)}
    </span>
  ),
  priority: ({ value }) => <span className="block text-right tabular-nums">{String(value ?? '')}</span>,
  updated_at: (params) => <DateTimeCell {...params} />,
}
