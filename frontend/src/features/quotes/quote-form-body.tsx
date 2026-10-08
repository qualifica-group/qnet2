import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { QuoteCreateLines } from '@/features/quotes/quote-create-lines'
import { QuoteCreateSections } from '@/features/quotes/quote-create-sections'
import { QuoteFormHeader } from '@/features/quotes/quote-form-header'
import { useQuoteCreateDefaults } from '@/features/quotes/use-quote-create-defaults'
import { useQuoteForm } from '@/features/quotes/use-quote-form'
import type { QuoteCreateFormMode, QuoteDetail } from '@/features/quotes/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const QUOTE_FORM_ID = 'quote-form'

interface QuoteFormBodyProps {
  mode: QuoteCreateFormMode
  onSuccess: (quote: QuoteDetail) => void
  onCancel: () => void
  /** The sequential code suggestion prefilled into the `code` field (D-13/AC-082). */
  initialCode?: string
}

/**
 * The quote create form UI, a replica of the quote detail (spec 0197 D-6, the
 * Commesse model of spec 0196): the same `RecordCanvas`, the record card with
 * its identity band, live KPI strip and sections, every row closed until
 * clicked (`QuoteCreateSections`), then the Offerta/Costi grids and the live
 * summary (`QuoteCreateLines`). There is no edit form: the detail edits a
 * persisted quote in place.
 *
 * Every field sits in `MetaField` (spec 0004): hidden means absent,
 * non-editable means disabled, `required` comes from the resolved
 * `ResourcePermissions`. Pure composition: the form lives in `useQuoteForm`,
 * the create-only prefills in `useQuoteCreateDefaults`.
 */
export function QuoteFormBody({ mode, onSuccess, onCancel, initialCode }: QuoteFormBodyProps) {
  const { t } = useTranslation()
  const quoteForm = useQuoteForm({ mode, onSuccess, initialCode })
  const defaults = useQuoteCreateDefaults(mode, quoteForm)
  const { form, onSubmit } = quoteForm
  const draft = useDraftInlineEdit(form)

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={QUOTE_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordBody side={null}>
            <RecordCard>
              <QuoteFormHeader quoteForm={quoteForm} formId={QUOTE_FORM_ID} onCancel={onCancel} />
              <QuoteCreateSections quoteForm={quoteForm} draft={draft} defaults={defaults} />
              <QuoteCreateLines quoteForm={quoteForm} />
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={QUOTE_FORM_ID}
              isSubmitting={form.formState.isSubmitting}
              submitLabel={t('quotes.form.save')}
              submittingLabel={t('quotes.form.saving')}
              cancel={{ label: t('quotes.form.cancel'), onCancel }}
            />
          </RecordBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
