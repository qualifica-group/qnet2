import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ClipboardList, Loader2, NotebookText, TrendingDown, TrendingUp } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { FORM_TAB_LIST_CLASS, FORM_TAB_TRIGGER_CLASS, TabErrorDot } from '@/components/form-tab-strip'
import { FormSection } from '@/components/form-section'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import {
  OPPORTUNITIES_FOR_SELECT_RESOURCE,
  type OpportunityForSelectItem,
  type OpportunityForSelectMeta,
} from '@/features/opportunities/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { QUOTE_STATUSES_FOR_SELECT_RESOURCE } from '@/features/quote-statuses/for-select-api'
import { QuoteOfferTab } from '@/features/quotes/quote-offer-tab'
import { QuoteCostsTab } from '@/features/quotes/quote-costs-tab'
import { QuoteNotesTab } from '@/features/quotes/quote-notes-tab'
import { QuoteLiveSummary } from '@/features/quotes/quote-summary'
import { useQuoteForm } from '@/features/quotes/use-quote-form'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteDetail, QuoteFormMode } from '@/features/quotes/types'

interface QuoteFormBodyProps {
  mode: QuoteFormMode
  onSuccess: (quote: QuoteDetail) => void
  onCancel: () => void
  /** Create-only: the sequential code suggestion prefilled into the `code` field (D-13/AC-082). */
  initialCode?: string
}

const OFFER_TAB = 'offer'
const COSTS_TAB = 'costs'
const NOTES_TAB = 'notes'

/** Empty placeholders when the form's own `formState.errors` has no array-level issue for that tab. */
const NO_ROW_ERRORS: undefined = undefined

/**
 * The quote create/edit form UI (spec 0065 AC-070): testata fields (code,
 * title, opportunity, quote status, commercial/reporter/supervisor) OUTSIDE
 * the tabs, then a compact tab strip (Offerta/Costi/Note) and, always
 * visible below it regardless of the active tab, the live economic summary
 * (`QuoteLiveSummary`, AC-071). Every field is wrapped in `MetaField`
 * (spec 0004, AC-077); all non-render logic lives in `useQuoteForm`.
 */
export function QuoteFormBody({ mode, onSuccess, onCancel, initialCode }: QuoteFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit, vatRatePercentFor, rememberVatRatePercent } = useQuoteForm({
    mode,
    onSuccess,
    initialCode,
  })
  const original = mode.type === 'edit' ? mode.quote : null

  // Directive 2026-07-29: Commerciale, Segnalatore and Supervisore are always
  // inherited from the picked Opportunita' — hydrated straight from its
  // for-select `meta` (no extra fetch), then freely editable (spec 0065 D-3:
  // the quote keeps a snapshot, never a live link). Wired as the
  // opportunity select's `onItemChange`, so it only ever runs on an actual
  // user pick/clear, never as a render-time effect that could overwrite a
  // later edit.
  const [inheritedRoles, setInheritedRoles] = useState<OpportunityForSelectMeta | null>(null)
  const handleOpportunityItemChange = (item: ForSelectItem | null) => {
    const meta = (item as OpportunityForSelectItem | null)?.meta ?? null
    setInheritedRoles(meta)
    form.setValue('commercial_id', meta?.commercial?.id ?? null, { shouldDirty: true })
    form.setValue('reporter_id', meta?.reporter?.id ?? null, { shouldDirty: true })
    form.setValue('supervisor_id', meta?.supervisor?.id ?? null, { shouldDirty: true })
  }

  /** The inherited ref wins over the loaded quote's own, so the trigger relabels the moment it auto-fills. */
  const roleRef = (
    key: keyof OpportunityForSelectMeta,
    loaded: RelationFieldRef | null,
  ): RelationFieldRef | null => (inheritedRoles ? inheritedRoles[key] : loaded)

  const relationLabels = {
    placeholder: t('quotes.form.selectPlaceholder'),
    emptyLabel: t('quotes.form.selectEmpty'),
    errorLabel: t('quotes.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  const errors = form.formState.errors
  const offerErrors = (errors.offer_lines as unknown as (QuoteLineRowErrors | undefined)[] | undefined) ?? NO_ROW_ERRORS
  const costErrors = (errors.cost_lines as unknown as (QuoteLineRowErrors | undefined)[] | undefined) ?? NO_ROW_ERRORS
  const offerHasError = Boolean(errors.offer_lines)
  const costHasError = Boolean(errors.cost_lines)
  const notesHasError = Boolean(errors.internal_notes)
  const tabHasErrorsLabel = t('quotes.form.tabs.tabHasErrors')

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          <FormSection
            icon={ClipboardList}
            title={t('quotes.form.sections.identity.title')}
            description={t('quotes.form.sections.identity.description')}
          >
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <MetaField control={form.control} name="code" metaKey="code" label={t('quotes.form.code')}>
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

              <MetaField control={form.control} name="title" metaKey="title" label={t('quotes.form.title')}>
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <RelationSelectField
                control={form.control}
                name="opportunity_id"
                metaKey="opportunity_id"
                label={t('quotes.form.opportunity')}
                resource={OPPORTUNITIES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('quotes.form.opportunitySearch')}
                selected={original ? { id: original.opportunity.id, name: original.opportunity.name } : null}
                onItemChange={handleOpportunityItemChange}
                {...relationLabels}
              />

              <RelationSelectField
                control={form.control}
                name="quote_status_id"
                metaKey="quote_status_id"
                label={t('quotes.form.quoteStatus')}
                resource={QUOTE_STATUSES_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('quotes.form.quoteStatusSearch')}
                selected={original ? { id: original.quote_status.id, name: original.quote_status.name } : null}
                {...relationLabels}
              />

              <RelationSelectField
                control={form.control}
                name="commercial_id"
                metaKey="commercial_id"
                label={t('quotes.form.commercial')}
                resource={REFERENTS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('quotes.form.commercialSearch')}
                selected={roleRef('commercial', original?.commercial ?? null)}
                {...relationLabels}
              />

              <RelationSelectField
                control={form.control}
                name="reporter_id"
                metaKey="reporter_id"
                label={t('quotes.form.reporter')}
                resource={REFERENTS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('quotes.form.reporterSearch')}
                selected={roleRef('reporter', original?.reporter ?? null)}
                {...relationLabels}
              />

              <RelationSelectField
                control={form.control}
                name="supervisor_id"
                metaKey="supervisor_id"
                label={t('quotes.form.supervisor')}
                resource={USERS_FOR_SELECT_RESOURCE}
                searchPlaceholder={t('quotes.form.supervisorSearch')}
                selected={roleRef('supervisor', original?.supervisor ?? null)}
                showAvatar
                {...relationLabels}
              />
            </div>
          </FormSection>

          <Tabs defaultValue={OFFER_TAB} className="flex flex-col gap-4">
            <TabsList className={FORM_TAB_LIST_CLASS}>
              <TabsTrigger value={OFFER_TAB} className={FORM_TAB_TRIGGER_CLASS}>
                <TrendingUp aria-hidden="true" />
                {t('quotes.form.tabs.offer')}
                {offerHasError && <TabErrorDot label={tabHasErrorsLabel} />}
              </TabsTrigger>
              <TabsTrigger value={COSTS_TAB} className={FORM_TAB_TRIGGER_CLASS}>
                <TrendingDown aria-hidden="true" />
                {t('quotes.form.tabs.costs')}
                {costHasError && <TabErrorDot label={tabHasErrorsLabel} />}
              </TabsTrigger>
              <TabsTrigger value={NOTES_TAB} className={FORM_TAB_TRIGGER_CLASS}>
                <NotebookText aria-hidden="true" />
                {t('quotes.form.tabs.notes')}
                {notesHasError && <TabErrorDot label={tabHasErrorsLabel} />}
              </TabsTrigger>
            </TabsList>

            <TabsContent value={OFFER_TAB} className="flex flex-col gap-4">
              <QuoteOfferTab
                control={form.control}
                errors={offerErrors}
                knownLines={original?.offer_lines ?? []}
                vatRatePercentFor={vatRatePercentFor}
                rememberVatRatePercent={rememberVatRatePercent}
              />
            </TabsContent>

            <TabsContent value={COSTS_TAB} className="flex flex-col gap-4">
              <QuoteCostsTab
                control={form.control}
                errors={costErrors}
                knownLines={original?.cost_lines ?? []}
                vatRatePercentFor={vatRatePercentFor}
                rememberVatRatePercent={rememberVatRatePercent}
              />
            </TabsContent>

            <TabsContent value={NOTES_TAB} className="flex flex-col gap-4">
              <QuoteNotesTab control={form.control} />
            </TabsContent>
          </Tabs>

          {/* Always visible below the tabs, whichever tab is active (AC-070). */}
          <QuoteLiveSummary control={form.control} vatRatePercentFor={vatRatePercentFor} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="sticky bottom-0 z-10 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>
              {t('quotes.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting && <Loader2 className="mr-2 size-4 animate-spin" aria-hidden="true" />}
              {form.formState.isSubmitting ? t('quotes.form.saving') : t('quotes.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
