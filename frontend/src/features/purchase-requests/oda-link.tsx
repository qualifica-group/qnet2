import { Link } from 'react-router-dom'
import type { PurchaseOrderRef } from '@/features/purchase-requests/types'

const PURCHASE_ORDERS_PATH = '/purchase-orders'
const ODA_PLACEHOLDER = '—'

interface OdaLinkProps {
  purchaseOrder: PurchaseOrderRef | null
  /** Legacy free-text reference, shown as plain text when the line is not linked to an ODA. */
  odaReference?: string | null
  className?: string
}

/** ODA of an RDA line: a link to the order when linked (spec 0210 AC-022), else the legacy reference or a dash. */
export function OdaLink({ purchaseOrder, odaReference = null, className }: OdaLinkProps) {
  if (purchaseOrder) {
    return (
      <Link to={`${PURCHASE_ORDERS_PATH}/${purchaseOrder.id}`} className={`text-primary underline-offset-2 hover:underline ${className ?? ''}`}>
        {purchaseOrder.code}
      </Link>
    )
  }
  return <span className={className}>{odaReference || ODA_PLACEHOLDER}</span>
}
