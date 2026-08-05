import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FIELD_STACK_CLASS } from '@/components/record-form/layout'
import { ReporterRewardsField } from '@/components/record-form/reporter-rewards-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { OpportunityContactRecap } from '@/features/opportunities/opportunity-contact-recap'
import type { RewardAssignmentValue } from '@/features/opportunities/reward-assignment-field'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** i18n root of the reward block's strings, resolved inside `ReporterRewardsField`. */
const REWARDS_LABEL_PREFIX = 'opportunities.form.rewards'

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
 * immediately under this field.
 *
 * Same flow as Gestione Richieste (user directive 2026-08-05): the reward
 * block is the shared `ReporterRewardsField`, so it APPEARS — tinted inset,
 * motion-safe reveal — only once a Segnalatore is picked, instead of standing
 * there permanently disabled with a "pick a reporter first" hint. That hint
 * survives for the one case that needs it: a reporter cleared while rewards
 * are still attached, where the block is the only control able to detach them.
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
    <div className={FIELD_STACK_CLASS}>
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
      <ReporterRewardsField
        labelPrefix={REWARDS_LABEL_PREFIX}
        reporterId={reporterId}
        value={rewards}
        onChange={onRewardsChange}
        initialAssignments={initialRewards}
      />
    </div>
  )
}
