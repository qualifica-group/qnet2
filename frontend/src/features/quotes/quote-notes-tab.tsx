import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { NotebookText } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'

interface QuoteNotesTabProps {
  control: Control<QuoteFormValues>
  /** The loaded quote's hydrated payment method (edit mode), `null` on create. */
  selectedPaymentMethod: RelationFieldRef | null
  labels: {
    placeholder: string
    emptyLabel: string
    errorLabel: string
    clearLabel: string
    retryLabel: string
  }
}

/**
 * "Note e pagamenti" tab (directive 2026-07-30): the agreed payment method
 * (`payment_method_id`, the first consumer of the spec 0068 lookup — only
 * active methods are listed, the for-select's own contract) plus the
 * free-text `internal_notes` (max 5000 chars), never shown to the customer.
 *
 * The picker takes no `params`: unlike the layout one it is not scoped to a
 * module, and there is no default to resolve — an unset value stays null.
 */
export function QuoteNotesTab({ control, selectedPaymentMethod, labels }: QuoteNotesTabProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={NotebookText}
      title={t('quotes.form.sections.notes.title')}
      description={t('quotes.form.sections.notes.description')}
    >
      <RelationSelectField
        control={control}
        name="payment_method_id"
        metaKey="payment_method_id"
        label={t('quotes.form.paymentMethod')}
        resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('quotes.form.paymentMethodSearch')}
        selected={selectedPaymentMethod}
        {...labels}
      />

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
