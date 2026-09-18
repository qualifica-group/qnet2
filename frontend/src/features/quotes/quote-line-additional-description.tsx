import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { ADDITIONAL_DESCRIPTION_MAX_LENGTH } from '@/features/quotes/quote-schema'

interface QuoteLineAdditionalDescriptionProps {
  lineNumber: number
  value: string | null
  disabled: boolean
  onChange: (value: string | null) => void
}

/**
 * The line's optional free text, printed in the quote document through the
 * products_table `additional_description` column key. Collapsed to a single
 * compact action until the operator opens it, so a line without one costs no
 * vertical space beyond that action; removing it clears the value.
 */
export function QuoteLineAdditionalDescription({ lineNumber, value, disabled, onChange }: QuoteLineAdditionalDescriptionProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(Boolean(value))

  if (!open) {
    return (
      <Button
        type="button"
        variant="ghost"
        size="sm"
        className="h-6 justify-self-start px-2 text-xs text-muted-foreground"
        disabled={disabled}
        onClick={() => setOpen(true)}
      >
        <Plus aria-hidden="true" className="size-3.5" />
        {t('quotes.form.lineAdditionalDescriptionAdd')}
      </Button>
    )
  }

  return (
    <div className="flex items-start gap-2">
      <Textarea
        aria-label={t('quotes.form.lineAdditionalDescription', { n: lineNumber })}
        placeholder={t('quotes.form.lineAdditionalDescriptionPlaceholder')}
        className="min-h-9 py-1.5 md:text-xs"
        maxLength={ADDITIONAL_DESCRIPTION_MAX_LENGTH}
        disabled={disabled}
        value={value ?? ''}
        onChange={(event) => onChange(event.target.value === '' ? null : event.target.value)}
      />
      <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        aria-label={t('quotes.form.lineAdditionalDescriptionRemove', { n: lineNumber })}
        disabled={disabled}
        onClick={() => {
          onChange(null)
          setOpen(false)
        }}
      >
        <X aria-hidden="true" />
      </Button>
    </div>
  )
}
