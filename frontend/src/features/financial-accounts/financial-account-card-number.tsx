import { Eye } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { useRevealCardNumber } from '@/features/financial-accounts/use-reveal-card-number'

interface FinancialAccountCardNumberProps {
  accountId: number
  masked: string | null
  /** `permissions.actions.reveal_card_number`: the button is an affordance, the endpoint re-authorizes. */
  canReveal: boolean
}

/** Masked card number with an opt-in "Show number" action that reveals the full value. */
export function FinancialAccountCardNumber({
  accountId,
  masked,
  canReveal,
}: FinancialAccountCardNumberProps) {
  const { t } = useTranslation()
  const reveal = useRevealCardNumber(accountId)

  if (reveal.data) {
    return <span className="font-mono">{reveal.data}</span>
  }

  return (
    <span className="flex flex-wrap items-center gap-2">
      <span className="font-mono">{masked ?? '-'}</span>
      {canReveal ? (
        <Button
          type="button"
          variant="secondary"
          size="sm"
          onClick={() => reveal.mutate()}
          disabled={reveal.isPending}
        >
          <Eye aria-hidden="true" />
          {t('financialAccounts.detail.revealNumber')}
        </Button>
      ) : null}
      {reveal.isError ? (
        <span className="text-xs text-destructive" role="alert">
          {t('financialAccounts.detail.revealError')}
        </span>
      ) : null}
    </span>
  )
}
