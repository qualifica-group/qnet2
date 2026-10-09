import { useTranslation } from 'react-i18next'
import { Save } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { PurchaseTextField } from '@/features/purchase-requests/purchase-request-fields'
import { PurchaseRequestFormActions } from '@/features/purchase-requests/purchase-request-form-actions'
import { PurchaseRequestHeaderFields } from '@/features/purchase-requests/purchase-request-header-fields'
import { PurchaseRequestLinesSection } from '@/features/purchase-requests/purchase-request-lines-section'
import { requestRefs } from '@/features/purchase-requests/purchase-request-refs'
import { RequestDocuments } from '@/features/purchase-requests/purchase-request-documents'
import { RequestStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import { usePurchaseRequestForm } from '@/features/purchase-requests/use-purchase-request-form'
import type { PurchaseRequest } from '@/features/purchase-requests/types'

const FORM_ID = 'purchase-request-form'
const SECTION_CLASS = 'flex flex-col gap-3 rounded-lg border bg-card p-3'
const FOOTER_FIELDS = [
  'notes',
  'delivery_terms',
  'procurement_plan',
  'technical_requirements',
  'special_conditions',
] as const

interface PurchaseRequestFormProps {
  /** The loaded RDA (edit); absent while creating. */
  request?: PurchaseRequest
  /** Signed-in user: default requester and, while creating, the read-only author. */
  currentUser: { id: number; name: string }
  onSaved: (saved: PurchaseRequest) => void
  onCancel: () => void
  onDeleted: () => void
}

/**
 * Create/edit page body of an RDA: header, lines, footer texts, documents and
 * totals. Expects a `ResourcePermissionsProvider` above it (field matrix). A
 * closed RDA, or one the actor cannot update, renders fully read-only.
 */
export function PurchaseRequestForm({ request, currentUser, onSaved, onCancel, onDeleted }: PurchaseRequestFormProps) {
  const { t } = useTranslation()
  const { form, lineArray, lines, companyId, formErrors, addLine, submit, isSaving } = usePurchaseRequestForm({
    request,
    requesterId: currentUser.id,
    onSaved,
  })
  const readOnly = request !== undefined && (request.status === 'closed' || !request.abilities.update)

  return (
    <Form {...form}>
      <form id={FORM_ID} noValidate onSubmit={submit} className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <h1 className="text-xl font-semibold">
              {request
                ? t('purchaseRequests.form.editTitle', { id: request.id })
                : t('purchaseRequests.form.createTitle')}
            </h1>
            {request ? <RequestStatusBadge status={request.status} /> : null}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {request ? (
              <PurchaseRequestFormActions request={request} onClosed={onSaved} onDeleted={onDeleted} />
            ) : null}
            <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onCancel}>
              {t('common.back')}
            </Button>
            {readOnly ? null : (
              <Button type="submit" size="sm" disabled={isSaving}>
                <Save aria-hidden="true" />
                {t('purchaseRequests.form.save')}
              </Button>
            )}
          </div>
        </div>

        {request?.status === 'closed' ? (
          <p role="status" className="rounded-md border bg-surface px-3 py-2 text-xs">
            {t('purchaseRequests.form.closedNotice', { reason: request.close_reason ?? '—' })}
          </p>
        ) : null}
        {formErrors.length > 0 ? (
          <ul role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
            {formErrors.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        ) : null}

        <section className={SECTION_CLASS} aria-label={t('purchaseRequests.sections.header')}>
          <h2 className="text-base font-semibold">{t('purchaseRequests.sections.header')}</h2>
          <PurchaseRequestHeaderFields
            control={form.control}
            setValue={form.setValue}
            refs={requestRefs(request)}
            companyId={companyId}
            createdByName={request?.created_by.name ?? currentUser.name}
          />
        </section>

        <PurchaseRequestLinesSection
          control={form.control}
          setValue={form.setValue}
          fields={lineArray.fields}
          lines={lines}
          onAdd={addLine}
          onRemove={lineArray.remove}
          request={request}
          readOnly={readOnly}
          error={form.formState.errors.lines?.root?.message ?? form.formState.errors.lines?.message}
        />

        <section className={SECTION_CLASS} aria-label={t('purchaseRequests.sections.footer')}>
          <h2 className="text-base font-semibold">{t('purchaseRequests.sections.footer')}</h2>
          <div className="grid gap-3 md:grid-cols-2">
            {FOOTER_FIELDS.map((name) => (
              <PurchaseTextField key={name} control={form.control} name={name} multiline />
            ))}
          </div>
        </section>

        <section className={SECTION_CLASS} aria-label={t('purchaseRequests.sections.documents')}>
          <h2 className="text-base font-semibold">{t('purchaseRequests.sections.documents')}</h2>
          <RequestDocuments control={form.control} requestId={request?.id} />
        </section>
      </form>
    </Form>
  )
}
