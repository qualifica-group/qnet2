import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { NotebookText } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'

interface QuoteNotesTabProps {
  control: Control<QuoteFormValues>
}

/** Internal notes tab: a single free-text field (`internal_notes`, max 5000 chars), never shown to the customer. */
export function QuoteNotesTab({ control }: QuoteNotesTabProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={NotebookText}
      title={t('quotes.form.sections.notes.title')}
      description={t('quotes.form.sections.notes.description')}
    >
      <MetaField
        control={control}
        name="internal_notes"
        metaKey="internal_notes"
        label={t('quotes.form.internalNotes')}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea
              disabled={disabled}
              readOnly={readOnly}
              rows={8}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(event.target.value || null)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}
