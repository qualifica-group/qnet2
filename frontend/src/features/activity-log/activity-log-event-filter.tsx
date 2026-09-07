import { useTranslation } from 'react-i18next'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { ActivityLogEventFilter } from '@/features/activity-log/types'

export interface ActivityLogEventFilterTabsProps {
  value: ActivityLogEventFilter
  onChange: (value: ActivityLogEventFilter) => void
}

/** Selectable filters, in display order; `all` clears the server-side filter. */
const FILTER_OPTIONS: ActivityLogEventFilter[] = ['all', 'created', 'updated']

/**
 * Compact strip that narrows the timeline to a single operation type. Rendered
 * above the feed and never hidden by the feed's own states, so a filter that
 * matches nothing can always be switched back.
 */
export function ActivityLogEventFilterTabs({ value, onChange }: ActivityLogEventFilterTabsProps) {
  const { t } = useTranslation()

  return (
    <Tabs
      value={value}
      onValueChange={(next) => onChange(next as ActivityLogEventFilter)}
      aria-label={t('activityLog.filter.label')}
    >
      <TabsList className="h-8 w-fit">
        {FILTER_OPTIONS.map((option) => (
          <TabsTrigger key={option} value={option} className="px-2.5 py-1 text-xs">
            {t(`activityLog.filter.${option}`)}
          </TabsTrigger>
        ))}
      </TabsList>
    </Tabs>
  )
}
