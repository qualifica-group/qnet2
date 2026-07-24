import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { OpportunityContactRecap } from '@/features/opportunities/opportunity-contact-recap'
import {
  RewardAssignmentField,
  type RewardAssignmentValue,
} from '@/features/opportunities/reward-assignment-field'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { RewardAssignmentRef } from '@/features/rewards/types'

interface OpportunityReporterFieldProps {
  control: Control<OpportunityFormValues>
  selected: RelationFieldRef | null
  reporterId: number | null
  rewards: RewardAssignmentValue[]
  onRewardsChange: (next: RewardAssignmentValue[]) => void
  /** The loaded opportunity's persisted reward assignments (edit mode), `[]` on create. */
  initialRewards: RewardAssignmentRef[]
}

/**
 * The Segnalatore select plus its two dependents: the contacts recap (A-4)
 * and the reward "abbinamento" control (spec 0059 D-3), which must sit
 * immediately under this field. Split out of `OpportunityFormBody` (whose
 * three-column identity row was pushing the file past the 300-line soft
 * limit) rather than folded into `OpportunityContactRecap`, which stays a
 * read-only recap shared by all three relation columns.
 */
export function OpportunityReporterField({
  control,
  selected,
  reporterId,
  rewards,
  onRewardsChange,
  initialRewards,
}: OpportunityReporterFieldProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-1.5">
      <RelationSelectField
        control={control}
        name="reporter_id"
        metaKey="reporter_id"
        label={t('opportunities.form.reporter')}
        resource={REFERENTS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('opportunities.form.reporterSearch')}
        selected={selected}
        placeholder={t('opportunities.form.selectPlaceholder')}
        emptyLabel={t('opportunities.form.selectEmpty')}
        errorLabel={t('opportunities.form.selectError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />
      <OpportunityContactRecap referentId={reporterId} />
      <RewardAssignmentField
        value={rewards}
        onChange={onRewardsChange}
        initialAssignments={initialRewards}
        reporterId={reporterId}
        fieldLabel={t('opportunities.form.rewards.fieldLabel')}
        disabledHint={t('opportunities.form.rewards.reporterRequiredHint')}
        addLabel={t('opportunities.form.rewards.add')}
        removeLabel={(name) => t('opportunities.form.rewards.remove', { name })}
        searchPlaceholder={t('opportunities.form.rewards.searchPlaceholder')}
        emptyLabel={t('opportunities.form.rewards.empty')}
        errorLabel={t('opportunities.form.rewards.error')}
        retryLabel={t('common.retry')}
        loadMoreLabel={t('opportunities.form.rewards.loadMore')}
      />
    </div>
  )
}
