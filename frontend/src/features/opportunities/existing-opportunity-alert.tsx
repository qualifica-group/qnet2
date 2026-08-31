import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { CircleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { quoteCreateHref } from '@/features/quotes/quote-create-params'

interface ExistingOpportunityAlertProps {
  /** The opportunity that blocks the create — the link target. */
  opportunityId: number
  /** Why the create is blocked, already localized (i18n key or server message). */
  message: string
  /**
   * The products the refused create was carrying. When there are any, the
   * alert leads with the way out the user directive 2026-08-31 asks for: open
   * the offer form ON the blocking opportunity, rows already filled with them
   * — so the operator adds the offer there instead of duplicating the deal.
   */
  productIds?: readonly number[]
}

/**
 * The "an opportunity already exists" refusal, with the way out: the offer can
 * go on the opportunity that blocks the create (primary action, when the
 * refused form carried products), and the opportunity itself is one link away.
 * Shared by the two rules that produce the refusal — a lead already converted
 * (spec 0040, AC-087) and an anagrafica that still has an open opportunity
 * (user directive 2026-08-31) — so both look and behave the same.
 */
export function ExistingOpportunityAlert({
  opportunityId,
  message,
  productIds = [],
}: ExistingOpportunityAlertProps) {
  const { t } = useTranslation()

  return (
    <div
      role="alert"
      className="flex flex-col items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm text-destructive"
    >
      <p className="flex items-center gap-2 font-medium">
        <CircleAlert className="size-4 shrink-0" aria-hidden="true" />
        {message}
      </p>

      <div className="flex flex-wrap items-center gap-3">
        {productIds.length > 0 ? (
          <Button asChild size="sm" variant="secondary">
            <Link to={quoteCreateHref(opportunityId, productIds)}>
              {t('opportunities.form.addOfferToExistingOpportunity')}
            </Link>
          </Button>
        ) : null}

        <Link to={`/opportunities/${opportunityId}`} className="font-medium underline underline-offset-4">
          {t('opportunities.form.goToExistingOpportunity')}
        </Link>
      </div>
    </div>
  )
}
