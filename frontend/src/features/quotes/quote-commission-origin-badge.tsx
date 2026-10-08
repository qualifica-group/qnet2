import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import type { QuoteCommissionOrigin } from './types'

/** Scopes whose bare label ("Prodotto", "Categoria") reads as a name rather than as a rule. */
const RULE_PREFIXED: ReadonlySet<QuoteCommissionOrigin> = new Set(['PRODUCT', 'PRODUCT_CATEGORY'])

/**
 * Where a line commission comes from: "Regola: Prodotto/Categoria", "Regola
 * personale" or "Modifica manuale", with a tooltip spelling out what it
 * means (user directive 2026-10-07: the bare "Prodotto" was unclear).
 */
export function QuoteCommissionOriginBadge({ origin }: { origin: QuoteCommissionOrigin }) {
  const { t } = useTranslation()
  const manual = origin === 'MANUAL_OVERRIDE'
  const prefixed = RULE_PREFIXED.has(origin)

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <Badge
            tabIndex={0}
            variant={manual ? 'secondary' : 'outline'}
            className="cursor-help text-[11px] focus-visible:ring-[3px]"
          >
            {prefixed ? (
              <span className="font-normal text-muted-foreground">{t('quotes.form.commissions.originRule')}</span>
            ) : null}
            <span>{t(`quotes.form.commissions.origins.${origin}`)}</span>
          </Badge>
        </TooltipTrigger>
        <TooltipContent side="top" className="max-w-64 text-xs">
          {t(`quotes.form.commissions.originHints.${origin}`)}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
