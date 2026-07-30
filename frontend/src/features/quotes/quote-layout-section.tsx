import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control, UseFormSetValue } from 'react-hook-form'
import { FileText } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { flattenForSelectPages, useForSelect } from '@/features/for-select/use-for-select'
import { DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE } from '@/features/document-layouts/for-select-api'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteFormMode } from '@/features/quotes/types'

/**
 * The document-layouts for-select endpoint requires `module` (spec 0069):
 * scopes the list — and the resolved default below — to the `quotes` module.
 * Hoisted so the object identity is stable across renders (both the query's
 * `params` and the picker's own `params` prop key off it).
 */
const QUOTES_LAYOUT_MODULE_PARAM = { module: 'quotes' } as const

interface QuoteLayoutSectionProps {
  control: Control<QuoteFormValues>
  setValue: UseFormSetValue<QuoteFormValues>
  mode: QuoteFormMode
  labels: {
    placeholder: string
    emptyLabel: string
    errorLabel: string
    clearLabel: string
    retryLabel: string
  }
}

/**
 * Layout field (spec 0070 AC-310..AC-313), extracted into its own file like
 * `QuoteSitesSection` to keep `quote-form-body.tsx` under the size limit.
 *
 * CREATE (AC-310): the `quotes` module's active default layout is resolved
 * once (the for-select's own contract, spec 0069 — the predefined sorts
 * first) and applied to the still-empty field, so the user SEES it
 * preselected without picking. Applied via a ref-guarded effect, mirroring
 * `QuoteFormBody`'s `appliedForcedOpportunity`: the server would apply the
 * same default anyway when the key is absent (D-3), this only makes it
 * visible, and a later user pick is never overwritten once the query
 * eventually resolves.
 *
 * EDIT (AC-312): shows the persisted `quote.layout`, even when it has since
 * been deactivated — the shared `RelationSelectField`/`AsyncPaginatedSelect`
 * `ids[]` hydration resolves it regardless of `is_active`, and submit only
 * ever sends `layout_id` when the user actually changes it
 * (`buildUpdatePayload`), so an untouched field is never cleared.
 */
export function QuoteLayoutSection({ control, setValue, mode, labels }: QuoteLayoutSectionProps) {
  const { t } = useTranslation()
  const original = mode.type === 'edit' ? mode.quote : null

  const shouldResolveDefault = mode.type === 'create'
  const defaultLayoutQuery = useForSelect({
    resource: DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE,
    search: '',
    enabled: shouldResolveDefault,
    params: QUOTES_LAYOUT_MODULE_PARAM,
  })
  const defaultLayoutItem = flattenForSelectPages(defaultLayoutQuery.data?.pages)[0] ?? null

  const appliedDefault = useRef(false)
  useEffect(() => {
    if (!shouldResolveDefault || appliedDefault.current || !defaultLayoutItem) {
      return
    }
    appliedDefault.current = true
    setValue('layout_id', defaultLayoutItem.id, { shouldDirty: false })
  }, [shouldResolveDefault, defaultLayoutItem, setValue])

  const selected: RelationFieldRef | null = original?.layout
    ? { id: original.layout.id, name: original.layout.name }
    : defaultLayoutItem
      ? { id: defaultLayoutItem.id, name: defaultLayoutItem.label }
      : null

  return (
    <FormSection
      icon={FileText}
      title={t('quotes.form.sections.layout.title')}
      description={t('quotes.form.sections.layout.description')}
    >
      <RelationSelectField
        control={control}
        name="layout_id"
        metaKey="layout_id"
        label={t('quotes.form.layout')}
        resource={DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('quotes.form.layoutSearch')}
        selected={selected}
        params={QUOTES_LAYOUT_MODULE_PARAM}
        {...labels}
      />
    </FormSection>
  )
}
