import { useTranslation } from 'react-i18next'
import { RewardAssignmentField, type RewardAssignmentValue } from '@/features/opportunities/reward-assignment-field'
import { cn } from '@/lib/utils'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** Tinted inset — a veil over the card, never a rung of the surface scale (`ui-design.md §1-bis`). */
const BLOCK_CLASS = 'rounded-lg border bg-muted/40 px-3 py-2.5'

/** Same reveal every other conditional block of the app uses (see `opportunity-form-body.tsx`). */
const REVEAL_CLASS =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-top-1 motion-safe:duration-200'

interface RequestRewardsFieldProps {
  /** i18n root of the block's strings, e.g. `requestManagement.workPanel.attribution.rewards`. */
  labelPrefix: string
  reporterId: number | null
  value: RewardAssignmentValue[]
  onChange: (next: RewardAssignmentValue[]) => void
  /** The record's persisted assignments (`[]` on create), seeding each chip's name/color. */
  initialAssignments: RewardAssignmentRef[]
}

/**
 * The "abbinamento buono" control (spec 0059 D-3) as both Gestione Richieste
 * forms render it: the shared `RewardAssignmentField` in a tinted inset under
 * the Segnalatore, which is always the beneficiary — the inset is what makes
 * that binding explicit without moving the control elsewhere.
 *
 * It is mounted only once there IS a reporter (user directive 2026-08-04),
 * revealing itself with the app's standard motion-safe fade/slide; the exit is
 * an immediate unmount, like every other conditional block of the repo.
 *
 * The exception is a reporter cleared while rewards are still attached: the
 * backend refuses that pair with a 422 (`StoreRequestRequest`,
 * `UpdateRequestRequest`), so hiding the block there would hide the only
 * control able to detach them. It stays on screen, chips read-only, carrying
 * the "select a reporter first" hint.
 */
export function RequestRewardsField({
  labelPrefix,
  reporterId,
  value,
  onChange,
  initialAssignments,
}: RequestRewardsFieldProps) {
  const { t } = useTranslation()

  if (reporterId == null && value.length === 0) {
    return null
  }

  return (
    <RewardAssignmentField
      className={cn(BLOCK_CLASS, REVEAL_CLASS)}
      value={value}
      onChange={onChange}
      initialAssignments={initialAssignments}
      reporterId={reporterId}
      fieldLabel={t(`${labelPrefix}.fieldLabel`)}
      disabledHint={t(`${labelPrefix}.reporterRequiredHint`)}
      addLabel={t(`${labelPrefix}.add`)}
      removeLabel={(name) => t(`${labelPrefix}.remove`, { name })}
      searchPlaceholder={t(`${labelPrefix}.searchPlaceholder`)}
      emptyLabel={t(`${labelPrefix}.empty`)}
      errorLabel={t(`${labelPrefix}.error`)}
      retryLabel={t('common.retry')}
      loadMoreLabel={t(`${labelPrefix}.loadMore`)}
    />
  )
}
