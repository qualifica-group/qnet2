import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { ContractStatusRows } from '@/features/contract-statuses/contract-status-rows'
import { useContractStatusForm } from '@/features/contract-statuses/use-contract-status-form'
import type { ContractStatusDetail } from '@/features/contract-statuses/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const CONTRACT_STATUS_FORM_ID = 'contract-status-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface ContractStatusFormBodyProps {
  onSuccess: (contractStatus: ContractStatusDetail) => void
  onCancel: () => void
}

/**
 * The contract status create form as a replica of the detail (spec 0235): the
 * same rows, closed until clicked (Fatto/Ripristina, nothing saved per row);
 * Salva validates and creates everything at once.
 */
export function ContractStatusFormBody({ onSuccess, onCancel }: ContractStatusFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useContractStatusForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('contractStatuses.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={CONTRACT_STATUS_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('contractStatuses.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Flag />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('contractStatuses.form.save'),
          saving: t('contractStatuses.form.saving'),
          cancel: t('contractStatuses.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('contractStatuses.form.sections.identity.title')} full>
            <RecordFieldList>
              <ContractStatusRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
