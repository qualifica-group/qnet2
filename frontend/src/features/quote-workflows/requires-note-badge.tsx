import { useTranslation } from 'react-i18next'
import { StickyNote } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'

/**
 * The "note required" marker of a working status (spec 0047 amendment): the
 * single place the `requires_note` flag is rendered, so the configurator
 * editor and every status picker mark it identically. The flag is
 * configuration only — nothing enforces the note itself.
 */
export function RequiresNoteBadge({ className }: { className?: string }) {
  const { t } = useTranslation()

  return (
    <Badge className={cn('shrink-0 gap-1', BADGE_COLOR_CLASSES.amber, className)}>
      <StickyNote aria-hidden="true" className="size-3" />
      {t('quoteWorkflows.form.statuses.requiresNoteBadge')}
    </Badge>
  )
}
