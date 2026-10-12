import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { PanelRightClose } from 'lucide-react'
import { RecordCard } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

/** Compact trigger sizing of the collaboration tab strip (Opportunita' reference). */
export const RECORD_TAB_TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

export interface RecordCollaborationTab {
  value: string
  /** Trigger content after the icon: the label, optionally followed by a badge. */
  label: ReactNode
  icon: ReactNode
  content: ReactNode
}

interface RecordCollaborationCardProps {
  /** Already filtered by the caller's own authorization gates; empty = no card. */
  tabs: RecordCollaborationTab[]
  /** Controlled active tab; omitted, the first tab starts active. */
  value?: string
  onValueChange?: (value: string) => void
  /** Shows the "collapse" button that folds the side column into its rail. */
  onCollapse?: () => void
}

/**
 * The side-column collaboration surface shared by every record detail
 * (Note | Documenti | Attivita' and module-specific extras): one card, a
 * compact tab strip, the first tab active. Each module decides WHICH tabs it
 * has and gates each on its own authorization source; this component only
 * renders them, and renders nothing when none is left.
 */
export function RecordCollaborationCard({ tabs, value, onValueChange, onCollapse }: RecordCollaborationCardProps) {
  const { t } = useTranslation()

  if (tabs.length === 0) {
    return null
  }

  return (
    <RecordCard>
      <Tabs defaultValue={tabs[0].value} value={value} onValueChange={onValueChange} className="gap-0">
        <div className="flex items-start gap-2 px-4 py-3">
          <TabsList className="min-w-0 flex-1">
            {tabs.map((tab) => (
              <TabsTrigger key={tab.value} value={tab.value} className={RECORD_TAB_TRIGGER_CLASS}>
                {tab.icon}
                {tab.label}
              </TabsTrigger>
            ))}
          </TabsList>
          {onCollapse ? (
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              onClick={onCollapse}
              aria-label={t('common.recordSide.collapse')}
              title={t('common.recordSide.collapse')}
            >
              <PanelRightClose aria-hidden="true" />
            </Button>
          ) : null}
        </div>
        <div className="border-t" />
        <div className="min-w-0 p-4">
          {tabs.map((tab) => (
            <TabsContent key={tab.value} value={tab.value}>
              {tab.content}
            </TabsContent>
          ))}
        </div>
      </Tabs>
    </RecordCard>
  )
}
