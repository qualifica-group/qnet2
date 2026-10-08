import { useTranslation } from 'react-i18next'
import { HandCoins } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { cn } from '@/lib/utils'
import type { CommissionRole } from '@/features/commission-configurations/types'
import { formatQuoteAmount } from './quote-summary'
import { QuoteCommissionRoleRow, QuoteCommissionRowsHeader } from './quote-commission-role-row'
import { useQuoteCommissionsDraft } from './use-quote-commissions-draft'
import type { QuoteCommissionContext, QuoteLineCommissionInput } from './types'

const ROLES: CommissionRole[] = ['COMMERCIAL', 'REPORTER', 'SUPERVISOR', 'SUPPLIER']

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  lineNumber: number
  productName: string
  /** Half of the recipient lookup: the supplier role resolves off the line's product. */
  productId: number | null
  quantity: number | null
  unitPrice: number | null
  /**
   * Spec 0145 (D-1/D-2): the net of the costs THIS row already has imputed
   * to it (spec 0144 `offer_line_id`/`offer_line_key`) — subtracted from the
   * line's own net before a PERCENTAGE commission applies. `0` (default) =
   * no imputed cost, i.e. the base is the line's full net.
   */
  allocatedCostNet?: number
  commissions: QuoteLineCommissionInput[]
  disabled: boolean
  /** The other half: the quote's live role selections. Omitted only where the caller has none (cost lines never reach here). */
  commissionContext?: QuoteCommissionContext
  onSave: (commissions: QuoteLineCommissionInput[]) => void
}

interface BaseFigureProps {
  label: string
  value: number
  emphasis?: boolean
}

/** One figure of the header strip: how the line's commission base is built, and what it pays out. */
function BaseFigure({ label, value, emphasis = false }: BaseFigureProps) {
  return (
    <div className={cn('flex flex-col gap-0.5 rounded-md border px-3 py-2', emphasis ? 'border-primary/30 bg-primary/5' : 'bg-card')}>
      <dt className="truncate text-[11px] text-muted-foreground">{label}</dt>
      <dd className={cn('text-sm font-semibold tabular-nums', emphasis ? 'text-primary' : 'text-foreground')}>
        {formatQuoteAmount(value)}
      </dd>
    </div>
  )
}

/**
 * Per-line commission editor: a header that shows how the commission base is
 * built (line net, imputed costs, base) and what the line pays out, then one
 * row per role. The body is the only scrolling part: the dialog is a flex
 * column, since an `auto` grid row would grow past `max-h` and spill out.
 */
export function QuoteCommissionsDialog(props: Props) {
  const { t } = useTranslation()
  const commissions = useQuoteCommissionsDraft(props)

  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent size="lg" className="flex max-h-[85vh] flex-col gap-0 p-0">
        <DialogHeader className="shrink-0 gap-3 rounded-t-lg border-b bg-surface p-4 text-left">
          <div className="flex items-start gap-3 pr-6">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
              <HandCoins aria-hidden="true" className="size-4" />
            </span>
            <div className="flex min-w-0 flex-col gap-0.5">
              <DialogTitle className="text-base break-words">
                {t('quotes.form.commissions.title', { product: props.productName, n: props.lineNumber })}
              </DialogTitle>
              <DialogDescription className="text-xs">{t('quotes.form.commissions.description')}</DialogDescription>
            </div>
          </div>
          <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <BaseFigure label={t('quotes.form.commissions.base.lineNet')} value={commissions.lineNet} />
            <BaseFigure label={t('quotes.form.commissions.base.allocatedCost')} value={commissions.allocatedCostNet} />
            <BaseFigure label={t('quotes.form.commissions.base.base')} value={commissions.commissionBase} />
            <BaseFigure label={t('quotes.form.commissions.base.total')} value={commissions.totalAmount} emphasis />
          </dl>
        </DialogHeader>

        <div className="min-h-0 flex-1 overflow-y-auto p-4">
          <div className="overflow-hidden rounded-lg border bg-card">
            <QuoteCommissionRowsHeader />
            <ul className="divide-y">
              {ROLES.map((role) => (
                <QuoteCommissionRoleRow key={role} role={role} commissions={commissions} disabled={props.disabled} />
              ))}
            </ul>
          </div>
        </div>

        <DialogFooter className="shrink-0 rounded-b-lg border-t bg-surface px-4 py-3">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => props.onOpenChange(false)}>
            {props.disabled ? t('quotes.form.commissions.close') : t('common.cancel')}
          </Button>
          {commissions.canMutate ? (
            <Button
              type="button"
              size="sm"
              disabled={!commissions.canSave}
              onClick={() => {
                props.onSave(commissions.draft)
                props.onOpenChange(false)
              }}
            >
              {t('quotes.form.commissions.save')}
            </Button>
          ) : null}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
