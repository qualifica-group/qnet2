import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Gift } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { RewardTypeRows } from '@/features/reward-types/reward-type-rows'
import { useRewardTypeForm } from '@/features/reward-types/use-reward-type-form'
import type { RewardTypeDetail } from '@/features/reward-types/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const REWARD_TYPE_FORM_ID = 'reward-type-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface RewardTypeFormBodyProps {
  onSuccess: (rewardType: RewardTypeDetail) => void
  onCancel: () => void
}

/**
 * The reward type create form as a replica of the detail (spec 0235): the
 * same rows, closed until clicked (Fatto/Ripristina, nothing saved per row);
 * Salva validates and creates everything at once.
 */
export function RewardTypeFormBody({ onSuccess, onCancel }: RewardTypeFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useRewardTypeForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('rewardTypes.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={REWARD_TYPE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('rewardTypes.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Gift />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('rewardTypes.form.save'),
          saving: t('rewardTypes.form.saving'),
          cancel: t('rewardTypes.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('rewardTypes.form.sections.identity.title')} full>
            <RecordFieldList>
              <RewardTypeRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
