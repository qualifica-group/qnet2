import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { useAbilities } from '@/features/auth/use-abilities'
import { COMMISSION_SETTLEMENTS_DOMAIN } from '@/features/commission-settlements/types'
import type { CommissionSettlementRef } from '@/features/purchase-invoices/types'

/** "Da estratto n/anno": the commission statement this document was generated from, linked when the actor may view it. */
export function PurchaseInvoiceSettlementBadge({ settlement }: { settlement: CommissionSettlementRef }) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const label = t('purchaseInvoiceViews.detail.fromSettlement', { code: settlement.code })

  return (
    <Badge variant="secondary" className="font-mono">
      {can(`${COMMISSION_SETTLEMENTS_DOMAIN}.view`) ? (
        <Link to={`/${COMMISSION_SETTLEMENTS_DOMAIN}/${settlement.id}`} className="hover:underline">
          {label}
        </Link>
      ) : (
        label
      )}
    </Badge>
  )
}
