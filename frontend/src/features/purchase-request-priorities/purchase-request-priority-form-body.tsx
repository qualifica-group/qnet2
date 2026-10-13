import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { SignalHigh } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { PurchaseRequestPriorityRows } from '@/features/purchase-request-priorities/purchase-request-priority-rows'
import { usePurchaseRequestPriorityForm } from '@/features/purchase-request-priorities/use-purchase-request-priority-form'
import type { PurchaseRequestPriorityDetail } from '@/features/purchase-request-priorities/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const PURCHASE_REQUEST_PRIORITY_FORM_ID = 'purchase-request-priority-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface PurchaseRequestPriorityFormBodyProps {
  onSuccess: (purchaseRequestPriority: PurchaseRequestPriorityDetail) => void
  onCancel: () => void
}

/**
 * The RDA priority create form as a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function PurchaseRequestPriorityFormBody({ onSuccess, onCancel }: PurchaseRequestPriorityFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = usePurchaseRequestPriorityForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('purchaseRequestPriorities.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={PURCHASE_REQUEST_PRIORITY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('purchaseRequestPriorities.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<SignalHigh />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('purchaseRequestPriorities.form.save'),
          saving: t('purchaseRequestPriorities.form.saving'),
          cancel: t('purchaseRequestPriorities.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('purchaseRequestPriorities.form.sections.identity.title')} full>
            <RecordFieldList>
              <PurchaseRequestPriorityRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
