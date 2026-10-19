import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { HandCoins } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { QuoteCommissionsDialog } from '@/features/quotes/quote-commissions-dialog'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteCommissionContext } from '@/features/quotes/types'

interface QuoteLineCommissionsActionProps {
  /** 1-based row number: the row's name in the labels. */
  position: number
  row: QuoteLineFormValues
  productName: string | undefined
  commissionContext?: QuoteCommissionContext
  /** Spec 0145 (D-1): this row's imputed-cost net, the base of the commissions. */
  allocatedCostNet: number
  disabled: boolean
  onSave: (commissions: NonNullable<QuoteLineFormValues['commissions']>) => void
}

/**
 * The provvigioni control of a revenue row card (spec 0269): the same button
 * and dialog the former `QuoteLineRow` carried, now a slot of the shared line
 * editor. The dialog mounts only while open.
 */
export function QuoteLineCommissionsAction({
  position,
  row,
  productName,
  commissionContext,
  allocatedCostNet,
  disabled,
  onSave,
}: QuoteLineCommissionsActionProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <>
      <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        aria-label={t('quotes.form.commissions.action', { n: position })}
        disabled={row.product_id === null}
        onClick={() => setOpen(true)}
      >
        <HandCoins aria-hidden="true" />
      </Button>
      {open ? (
        <QuoteCommissionsDialog
          open={open}
          onOpenChange={setOpen}
          lineNumber={position}
          productName={productName ?? t('quotes.form.commissions.productFallback')}
          productId={row.product_id}
          commissionContext={commissionContext}
          quantity={row.quantity}
          unitPrice={row.unit_price}
          allocatedCostNet={allocatedCostNet}
          commissions={row.commissions ?? []}
          disabled={disabled}
          onSave={onSave}
        />
      ) : null}
    </>
  )
}
