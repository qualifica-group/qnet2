import { useTranslation } from 'react-i18next'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { NoteQuoteRef, NoteQuoteScope } from '@/features/notes/types'

/** Sentinelle testuali: `Select` di Radix lavora su stringhe, gli id sono numeri. */
const ALL_VALUE = 'all'
const GENERAL_VALUE = 'general'

interface NoteQuoteScopeSelectProps {
  value: NoteQuoteScope
  onChange: (scope: NoteQuoteScope) => void
  /** Le Offerte dell'Opportunita' ospite. Vuoto = il selettore non ha nulla da offrire. */
  quotes: NoteQuoteRef[]
  /** Etichetta accessibile: il filtro della lista e il selettore di destinazione dicono cose diverse. */
  label: string
  /** Il filtro ha la voce "Tutte"; la destinazione di una nota nuova no — o e' generale o e' di UNA offerta. */
  includeAll?: boolean
  disabled?: boolean
  className?: string
}

/**
 * Il selettore di contesto delle note (spec 0085, D-2), usato in due ruoli:
 * come FILTRO della lista (`includeAll`) e come DESTINAZIONE nel composer.
 *
 * Un solo componente per entrambi perche' il vocabolario e' identico — generale
 * oppure una specifica Offerta — e tenerli separati significherebbe che una
 * futura Offerta rinominata compare in un elenco e non nell'altro.
 *
 * Non si monta affatto quando l'Opportunita' non ha Offerte: il filtro
 * degenererebbe in un menu con una sola voce, e la destinazione non avrebbe
 * alternative da offrire.
 */
export function NoteQuoteScopeSelect({
  value,
  onChange,
  quotes,
  label,
  includeAll = false,
  disabled,
  className,
}: NoteQuoteScopeSelectProps) {
  const { t } = useTranslation()

  if (quotes.length === 0) {
    return null
  }

  const asString = value === 'all' ? ALL_VALUE : value === 'general' ? GENERAL_VALUE : String(value)

  return (
    <Select
      value={asString}
      onValueChange={(next) => {
        if (next === ALL_VALUE) {
          onChange('all')
          return
        }
        onChange(next === GENERAL_VALUE ? 'general' : Number(next))
      }}
      disabled={disabled}
    >
      <SelectTrigger className={className ?? 'h-8 w-auto min-w-40 text-xs'} aria-label={label}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {includeAll ? <SelectItem value={ALL_VALUE}>{t('notes.scope.all')}</SelectItem> : null}
        <SelectItem value={GENERAL_VALUE}>{t('notes.scope.general')}</SelectItem>
        {quotes.map((quote) => (
          <SelectItem key={quote.id} value={String(quote.id)}>
            {quote.code}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
