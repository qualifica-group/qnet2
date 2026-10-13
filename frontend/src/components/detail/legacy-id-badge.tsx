import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'

/**
 * The record's legacy id ("OLD ID #123") next to its title. The API sends
 * `old_id` only to an actor allowed to see it (`legacy-ids.view`), so its
 * absence alone hides the badge.
 */
export function LegacyIdBadge({ id }: { id: number }) {
  const { t } = useTranslation()

  return (
    <Badge variant="outline" className="px-1.5 py-0 text-[11px] font-normal text-muted-foreground tabular-nums">
      {t('common.legacyIdBadge', { id })}
    </Badge>
  )
}
