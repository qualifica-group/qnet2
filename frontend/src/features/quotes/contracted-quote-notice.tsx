import { Lock } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { isContractedConfirmRequired, isContractedFrozen, type ContractedQuoteFlags } from '@/features/quotes/contracted-quote'

/**
 * Spec 0404 E: compact notice on an offer under contract. Frozen for who lacks
 * the permission, a lighter "confirmation will be asked" line for who has it.
 */
export function ContractedQuoteNotice({ flags }: { flags: ContractedQuoteFlags }) {
  const { t } = useTranslation()
  const frozen = isContractedFrozen(flags)

  if (!frozen && !isContractedConfirmRequired(flags)) {
    return null
  }

  return (
    <p
      role="status"
      className="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs text-muted-foreground"
    >
      <Lock aria-hidden="true" className="size-3.5 shrink-0" />
      <span>{t(frozen ? 'common.contractedQuote.frozenNotice' : 'common.contractedQuote.confirmNotice')}</span>
    </p>
  )
}
