import { useTranslation } from 'react-i18next'
import { Building2, NotebookText, Paperclip, ShoppingCart } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { PurchaseTextField } from '@/features/purchase-requests/purchase-request-fields'
import { PurchaseRequestHeaderFields } from '@/features/purchase-requests/purchase-request-header-fields'
import { PurchaseRequestLinesSection } from '@/features/purchase-requests/purchase-request-lines-section'
import { requestRefs } from '@/features/purchase-requests/purchase-request-refs'
import { RequestDocuments } from '@/features/purchase-requests/purchase-request-documents'
import { usePurchaseRequestForm } from '@/features/purchase-requests/use-purchase-request-form'
import type { PurchaseRequest } from '@/features/purchase-requests/types'

const FORM_ID = 'purchase-request-form'
const FOOTER_FIELDS = [
  'notes',
  'delivery_terms',
  'procurement_plan',
  'technical_requirements',
  'special_conditions',
] as const

interface PurchaseRequestFormProps {
  /** Signed-in user: default requester and the read-only author. */
  currentUser: { id: number; name: string }
  onSaved: (saved: PurchaseRequest) => void
  onCancel: () => void
}

/**
 * Create form of an RDA (spec 0256 D-8): the same record card as the detail,
 * its fields open, and ONE Save that validates and creates header, lines and
 * the queued documents together. Expects a `ResourcePermissionsProvider` above
 * it (field matrix). Saved RDAs edit in place on the detail instead.
 */
export function PurchaseRequestForm({ currentUser, onSaved, onCancel }: PurchaseRequestFormProps) {
  const { t } = useTranslation()
  const { form, lineArray, lines, companyId, formErrors, addLine, submit, isSaving } = usePurchaseRequestForm({
    requesterId: currentUser.id,
    onSaved,
  })
  const subject = form.watch('subject')
  const title = subject.trim() === '' ? t('purchaseRequests.form.createTitle') : subject

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={submit}
        title={title}
        media={<DetailMonogram name={title} icon={<ShoppingCart />} className="size-10 text-base [&>svg]:size-5" />}
        isSubmitting={isSaving}
        serverError={formErrors.length > 0 ? formErrors.join(' ') : null}
        onCancel={onCancel}
        labels={{ save: t('purchaseRequests.form.save'), saving: t('purchaseRequests.form.saving'), cancel: t('common.cancel') }}
      >
        <div className="flex flex-col gap-5 p-4">
          <RecordSection title={t('purchaseRequests.sections.header')} icon={<Building2 />}>
            <PurchaseRequestHeaderFields
              control={form.control}
              setValue={form.setValue}
              refs={requestRefs(undefined)}
              companyId={companyId}
              createdByName={currentUser.name}
            />
          </RecordSection>

          <PurchaseRequestLinesSection
            control={form.control}
            setValue={form.setValue}
            fields={lineArray.fields}
            lines={lines}
            onAdd={addLine}
            onRemove={lineArray.remove}
            error={form.formState.errors.lines?.root?.message ?? form.formState.errors.lines?.message}
          />

          <RecordSection title={t('purchaseRequests.sections.footer')} icon={<NotebookText />}>
            <div className="grid gap-3 md:grid-cols-2">
              {FOOTER_FIELDS.map((name) => (
                <PurchaseTextField key={name} control={form.control} name={name} multiline />
              ))}
            </div>
          </RecordSection>

          <RecordSection title={t('purchaseRequests.sections.documents')} icon={<Paperclip />}>
            <RequestDocuments control={form.control} />
          </RecordSection>
        </div>
      </RecordCreateLayout>
    </Form>
  )
}
