import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { PurchaseOrderStatus } from '@/features/purchase-orders/types'

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline'

/** Token-based variant per status; the label always travels with it (never colour alone). */
const VARIANTS: Record<PurchaseOrderStatus, { variant: BadgeVariant; className?: string }> = {
  draft: { variant: 'outline' },
  in_validation: { variant: 'outline', className: 'border-dashed' },
  approved: { variant: 'secondary' },
  sent_to_supplier: { variant: 'default' },
  partially_fulfilled: { variant: 'default', className: 'bg-primary/80' },
  fulfilled: { variant: 'default', className: 'bg-primary/60' },
  cancelled: { variant: 'destructive' },
}

export function PurchaseOrderStatusBadge({ status, className }: { status: PurchaseOrderStatus; className?: string }) {
  const { t } = useTranslation()
  const { variant, className: tone } = VARIANTS[status]
  return (
    <Badge variant={variant} className={cn('text-[11px]', tone, className)}>
      {t(`purchaseOrders.statuses.${status}`)}
    </Badge>
  )
}
