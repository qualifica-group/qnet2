import { useTranslation } from 'react-i18next'
import { useAbsenceSummary } from '@/features/absences/summary/use-absence-summary'
import type { NavBadge } from '@/components/nav-main'

/** Above this the menu badge caps to "99+", the accessible label keeps the exact count. */
const MENU_BADGE_MAX = 99

/** Badge of the "Assenze" menu entry: requests the actor has to decide; `null` when there are none. */
export function useAbsenceMenuBadge(): NavBadge | null {
  const { t } = useTranslation()
  const { data } = useAbsenceSummary()
  const count = data?.to_approve_count ?? 0

  return count > 0
    ? {
        count,
        label: count > MENU_BADGE_MAX ? `${MENU_BADGE_MAX}+` : String(count),
        ariaLabel: t('absences.summary.menuBadgeAriaLabel', { count }),
      }
    : null
}
