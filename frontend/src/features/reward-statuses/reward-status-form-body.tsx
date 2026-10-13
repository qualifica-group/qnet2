import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { RewardStatusRows } from '@/features/reward-statuses/reward-status-rows'
import { useRewardStatusForm } from '@/features/reward-statuses/use-reward-status-form'
import type { RewardStatusDetail } from '@/features/reward-statuses/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const REWARD_STATUS_FORM_ID = 'reward-status-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface RewardStatusFormBodyProps {
  onSuccess: (rewardStatus: RewardStatusDetail) => void
  onCancel: () => void
}

/**
 * The reward status create form as a replica of the detail (spec 0235): the
 * same rows, closed until clicked (Fatto/Ripristina, nothing saved per row);
 * Salva validates and creates everything at once.
 */
export function RewardStatusFormBody({ onSuccess, onCancel }: RewardStatusFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useRewardStatusForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('rewardStatuses.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={REWARD_STATUS_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('rewardStatuses.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Flag />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('rewardStatuses.form.save'),
          saving: t('rewardStatuses.form.saving'),
          cancel: t('rewardStatuses.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('rewardStatuses.form.sections.identity.title')} full>
            <RecordFieldList>
              <RewardStatusRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
