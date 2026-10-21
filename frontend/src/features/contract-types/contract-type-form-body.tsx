import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FileSignature } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { ContractTypeRows } from '@/features/contract-types/contract-type-rows'
import { useContractTypeForm } from '@/features/contract-types/use-contract-type-form'
import type { ContractType } from '@/features/contract-types/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const FORM_ID = 'contract-type-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface ContractTypeFormBodyProps {
  onSuccess: (contractType: ContractType) => void
  onCancel: () => void
}

/**
 * The contract type create form as a replica of the detail: the same rows, closed until
 * clicked (nothing saved per row); Save validates and creates everything at once.
 */
export function ContractTypeFormBody({ onSuccess, onCancel }: ContractTypeFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useContractTypeForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('contractTypes.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('contractTypes.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<FileSignature />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('contractTypes.form.save'),
          saving: t('contractTypes.form.saving'),
          cancel: t('contractTypes.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('contractTypes.form.sections.identity.title')} full>
            <RecordFieldList>
              <ContractTypeRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
