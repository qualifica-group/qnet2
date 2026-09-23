import type { ReactNode } from 'react'
import { RecordCard } from '@/components/detail/record-panel'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

/** Compact trigger sizing of the collaboration tab strip (Opportunita' reference). */
const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

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
}

/**
 * The side-column collaboration surface shared by every record detail
 * (Note | Documenti | Attivita' and module-specific extras): one card, a
 * compact tab strip, the first tab active. Each module decides WHICH tabs it
 * has and gates each on its own authorization source; this component only
 * renders them, and renders nothing when none is left.
 */
export function RecordCollaborationCard({ tabs }: RecordCollaborationCardProps) {
  if (tabs.length === 0) {
    return null
  }

  return (
    <RecordCard>
      <Tabs defaultValue={tabs[0].value} className="gap-0">
        <div className="px-4 py-3">
          <TabsList>
            {tabs.map((tab) => (
              <TabsTrigger key={tab.value} value={tab.value} className={TRIGGER_CLASS}>
                {tab.icon}
                {tab.label}
              </TabsTrigger>
            ))}
          </TabsList>
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
