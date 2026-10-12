import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { PanelRightOpen } from 'lucide-react'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RecordCard } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Separator } from '@/components/ui/separator'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

/** Floats over the record, so it costs no layout space; both caps keep it on screen. */
const FLYOUT_CLASS =
  'flex h-[min(40rem,var(--radix-popover-content-available-height))] w-[32rem] max-w-[calc(100vw-2rem)] flex-col p-0'

interface RecordCollaborationRailProps {
  tabs: RecordCollaborationTab[]
  onExpand: () => void
}

/**
 * The collapsed side column: "expand" reopens the panel, each tab icon shows
 * that tab in a floating panel next to the rail without reopening it (closed
 * by clicking outside or Esc).
 */
export function RecordCollaborationRail({ tabs, onExpand }: RecordCollaborationRailProps) {
  const { t } = useTranslation()

  // Own provider: the rail stays mounted (hidden) beside the expanded card for the cross-fade.
  return (
    <TooltipProvider>
      <RecordCard className="flex flex-col items-center gap-1 p-1">
        <RailTooltip label={t('common.recordSide.expand')}>
          <Button type="button" variant="ghost" size="icon-sm" onClick={onExpand}>
            <PanelRightOpen aria-hidden="true" />
            <span className="sr-only">{t('common.recordSide.expand')}</span>
          </Button>
        </RailTooltip>
        <Separator />
        {tabs.map((tab) => (
          <Popover key={tab.value}>
            <RailTooltip label={tab.label}>
              <PopoverTrigger asChild>
                <Button type="button" variant="ghost" size="icon-sm">
                  {tab.icon}
                  <span className="sr-only">{tab.label}</span>
                </Button>
              </PopoverTrigger>
            </RailTooltip>
            <PopoverContent side="left" align="start" sideOffset={8} className={FLYOUT_CLASS}>
              <h3 className="flex shrink-0 items-center gap-1.5 border-b px-4 py-3 text-xs font-semibold [&>svg]:size-3.5">
                {tab.icon}
                {tab.label}
              </h3>
              <div className="min-w-0 overflow-y-auto p-4">{tab.content}</div>
            </PopoverContent>
          </Popover>
        ))}
      </RecordCard>
    </TooltipProvider>
  )
}

function RailTooltip({ label, children }: { label: ReactNode; children: ReactNode }) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>{children}</TooltipTrigger>
      <TooltipContent side="left">{label}</TooltipContent>
    </Tooltip>
  )
}
