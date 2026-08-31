import { useTranslation } from 'react-i18next'
import { useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FIELD_STACK_CLASS } from '@/components/record-form/layout'
import { ReporterRewardsField } from '@/components/record-form/reporter-rewards-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** i18n root of the reward block's strings, resolved inside `ReporterRewardsField`. */
const REWARDS_LABEL_PREFIX = 'quotes.form.rewards'

/** Hoisted: an inline `?? []` would hand `ReporterRewardsField` a new reference every render. */
const NO_ASSIGNMENTS: RewardAssignmentRef[] = []

interface QuoteReporterFieldProps {
  control: Control<QuoteFormValues>
  setValue: UseFormSetValue<QuoteFormValues>
  selected: RelationFieldRef | null
  /** The loaded offer's persisted reward assignments (edit mode), `[]` on create. */
  initialRewards?: RewardAssignmentRef[]
  labels: {
    placeholder: string
    emptyLabel: string
    errorLabel: string
    clearLabel: string
    retryLabel: string
  }
}

/**
 * The Offerta's Segnalatore select plus its "abbinamento buono" control
 * (user directive 2026-08-31: the same flow the Opportunita' form and the
 * Gestione Richieste panel already have — `OpportunityReporterField` is the
 * literal twin of this, minus the contacts recap, which is an Opportunita'-
 * only affordance).
 *
 * The block is the SHARED `ReporterRewardsField`, so it appears only once a
 * Segnalatore is picked and it hydrates from the same `rewards` wire shape
 * `QuoteResource` and `OpportunityResource` both emit.
 */
export function QuoteReporterField({
  control,
  setValue,
  selected,
  initialRewards,
  labels,
}: QuoteReporterFieldProps) {
  const { t } = useTranslation()
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const rewards = useWatch({ control, name: 'rewards' })

  return (
    <div className={FIELD_STACK_CLASS}>
      <RelationSelectField
        control={control}
        name="reporter_id"
        metaKey="reporter_id"
        label={t('quotes.form.reporter')}
        resource={REFERENTS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('quotes.form.reporterSearch')}
        selected={selected}
        {...labels}
      />
      <ReporterRewardsField
        labelPrefix={REWARDS_LABEL_PREFIX}
        reporterId={reporterId ?? null}
        value={rewards ?? []}
        onChange={(next) => setValue('rewards', next, { shouldDirty: true })}
        initialAssignments={initialRewards ?? NO_ASSIGNMENTS}
      />
    </div>
  )
}
