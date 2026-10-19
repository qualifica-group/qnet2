import { Crown } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import type { EmploymentRelationRef } from '@/features/users/types'

/**
 * Business functions as chips (spec 0275 D-9): a crown precedes the name of
 * every function whose id is in `managedIds`, like the time-entries team view.
 */
export function FunctionBadges({ refs, managedIds }: { refs: EmploymentRelationRef[]; managedIds: number[] }) {
  const { t } = useTranslation()
  if (refs.length === 0) {
    return <DetailEmpty />
  }
  return (
    <div className="flex flex-wrap gap-1">
      {refs.map((ref) => (
        <Badge key={ref.id} variant="secondary" className="max-w-full">
          {managedIds.includes(ref.id) ? (
            <Crown
              role="img"
              className="size-3 shrink-0 text-amber-500"
              aria-label={t('users.detail.employment.functionManager')}
            />
          ) : null}
          <span className="truncate">{ref.label}</span>
        </Badge>
      ))}
    </div>
  )
}
