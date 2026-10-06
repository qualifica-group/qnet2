import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE } from '@/features/document-layouts/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import { QUOTES_LAYOUT_MODULE_PARAM, quoteRelationLabels } from '@/features/quotes/quote-field-strings'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'

/*
 * The offer's own scalar fields and its document/payment pickers, one
 * component each (spec 0197, the 0195 D-3 rule): the SAME control is the
 * editor of a detail row and of a create-draft row. Every one sits in
 * `MetaField`: hidden means absent, non-editable means disabled, `required`
 * comes from the field permission.
 */

interface FieldProps {
  control: Control<QuoteFormValues>
}

interface RelationFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never falls back to `#id`. */
  selected: RelationFieldRef | null
}

/** `code`: editable only at creation (D-13/AC-082), the field permission says so. */
export function QuoteCodeField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="code" metaKey="code" label={t('quotes.form.code')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            autoComplete="off"
            disabled={disabled}
            readOnly={readOnly}
            placeholder={t('quotes.form.codePlaceholder')}
            {...field}
            value={field.value ?? ''}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** Spec 0171 rev.2: optional, blank = the automatic `<code> - <products>` title. */
export function QuoteTitleField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="title" metaKey="title" label={t('quotes.form.title')} hint={t('quotes.form.titleHint')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            autoComplete="off"
            placeholder={t('quotes.form.titlePlaceholder')}
            disabled={disabled}
            readOnly={readOnly}
            {...field}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

interface OpportunityFieldProps extends RelationFieldProps {
  /** A pick or clear: the create form inherits the Opportunita's roles, sede and G.A. from its `meta`. */
  onItemChange: (item: ForSelectItem | null) => void
  /** Spec 0067 AC-050/052: locked to the Opportunity the create params preset. */
  forceDisabled: boolean
  /** Spec 0199: only this anagrafica's opportunities are offered; `null`/absent = the whole list. */
  registryId?: number | null
}

/** "Opportunita'": chosen at creation only (AC-025), the field permission locks it afterwards. */
export function QuoteOpportunityField({
  control,
  selected,
  onItemChange,
  forceDisabled,
  registryId = null,
}: OpportunityFieldProps) {
  const { t } = useTranslation()
  const params = useMemo(() => (registryId !== null ? { registry_id: registryId } : undefined), [registryId])
  return (
    <RelationSelectField
      control={control}
      name="opportunity_id"
      metaKey="opportunity_id"
      label={t('quotes.form.opportunity')}
      resource={OPPORTUNITIES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('quotes.form.opportunitySearch')}
      selected={selected}
      onItemChange={onItemChange}
      forceDisabled={forceDisabled}
      params={params}
      {...quoteRelationLabels(t)}
    />
  )
}

/** "Note interne" (max 5000), never shown to the customer: an emptied box is `null`. */
export function QuoteInternalNotesField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="internal_notes" metaKey="internal_notes" label={t('quotes.form.internalNotes')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Textarea
            disabled={disabled}
            readOnly={readOnly}
            rows={6}
            value={field.value ?? ''}
            onChange={(event) => field.onChange(event.target.value || null)}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/**
 * Document-generation layout (spec 0070): a deactivated layout still shows on
 * the record it was picked for — the `ids[]` hydration resolves it regardless
 * of `is_active` (AC-312).
 */
export function QuoteLayoutField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="layout_id"
      metaKey="layout_id"
      label={t('quotes.form.layout')}
      resource={DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('quotes.form.layoutSearch')}
      selected={selected}
      params={QUOTES_LAYOUT_MODULE_PARAM}
      {...quoteRelationLabels(t)}
    />
  )
}

/** The agreed payment method (directive 2026-07-30): only active methods are offered, no default. */
export function QuotePaymentMethodField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="payment_method_id"
      metaKey="payment_method_id"
      label={t('quotes.form.paymentMethod')}
      resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('quotes.form.paymentMethodSearch')}
      selected={selected}
      {...quoteRelationLabels(t)}
    />
  )
}
