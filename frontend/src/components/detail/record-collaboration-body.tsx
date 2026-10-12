import { createContext, useContext, useRef, useState, type CSSProperties, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { FileText } from 'lucide-react'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_COLUMN_CLASS,
  RECORD_RAIL_COLUMNS_CLASS,
  RECORD_SIDE_COLUMNS_CLASS,
} from '@/components/detail/record-layout'
import {
  RECORD_TAB_TRIGGER_CLASS,
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { RecordCollaborationRail } from '@/components/detail/record-collaboration-rail'
import { useRecordSideCollapsed, useRecordSideLayout } from '@/components/detail/use-record-side-layout'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { cn } from '@/lib/utils'

/** Value of the record's own tab when the collaboration tabs join it (narrow layout). */
const DETAILS_TAB = 'details'

/** Open/close of the side column: its track resizes while card and rail cross-fade. */
const TRACK_TRANSITION_CLASS = 'transition-[grid-template-columns] duration-300 ease-in-out motion-reduce:transition-none'
const FADE_TRANSITION_CLASS = 'transition-[opacity,visibility] duration-300 ease-in-out motion-reduce:transition-none'

/** The layer (card or rail) not currently shown: out of flow, invisible, not clickable. */
const HIDDEN_LAYER_CLASS = 'pointer-events-none invisible absolute top-0 left-0 opacity-0'

interface InlineTabsState {
  tabs: RecordCollaborationTab[]
  value: string
  onValueChange: (value: string) => void
}

/** Set only in the narrow layout: `RecordDetailsTabs` then renders the shared strip. */
const InlineTabsContext = createContext<InlineTabsState | null>(null)

interface RecordCollaborationBodyProps {
  children: ReactNode
  /** Non-empty, already filtered by the caller's authorization gates. */
  tabs: RecordCollaborationTab[]
}

/**
 * Record body whose side column is the collaboration card, in the layout the
 * body's real width allows (enterprise-CRM pattern):
 * - wide: the record + a fixed-width side column, collapsible (animated) to an
 *   icon rail whose icons open each tab in a floating panel (remembered per
 *   browser);
 * - narrow (tablet, phone, modal Sheet): no side column — its tabs join the
 *   record's own strip ("Dettagli | Note | ..."), placed by `RecordDetailsTabs`
 *   under the identity band and KPIs, instead of stacking below the record.
 */
export function RecordCollaborationBody({ children, tabs }: RecordCollaborationBodyProps) {
  const bodyRef = useRef<HTMLDivElement>(null)
  const layout = useRecordSideLayout(bodyRef)
  const { collapsed, setCollapsed } = useRecordSideCollapsed()
  const [sideTab, setSideTab] = useState(tabs[0].value)
  const [inlineTab, setInlineTab] = useState(DETAILS_TAB)
  // A tab can disappear (permissions refetched): fall back instead of showing an empty card.
  const hasTab = (value: string) => tabs.some((tab) => tab.value === value)
  const activeSideTab = hasTab(sideTab) ? sideTab : tabs[0].value
  const activeInlineTab = hasTab(inlineTab) ? inlineTab : DETAILS_TAB
  const isInline = layout === 'tabs'

  return (
    <InlineTabsContext value={isInline ? { tabs, value: activeInlineTab, onValueChange: setInlineTab } : null}>
      <div
        ref={bodyRef}
        className={cn(
          RECORD_BODY_GRID_CLASS,
          !isInline && TRACK_TRANSITION_CLASS,
          !isInline && (collapsed ? RECORD_RAIL_COLUMNS_CLASS : RECORD_SIDE_COLUMNS_CLASS),
        )}
      >
        <div className={RECORD_COLUMN_CLASS}>{children}</div>
        {isInline ? null : (
          <RecordCollaborationSide
            tabs={tabs}
            collapsed={collapsed}
            value={activeSideTab}
            onValueChange={setSideTab}
            onCollapse={() => setCollapsed(true)}
            onExpand={() => setCollapsed(false)}
          />
        )}
      </div>
    </InlineTabsContext>
  )
}

interface RecordCollaborationSideProps {
  tabs: RecordCollaborationTab[]
  collapsed: boolean
  value: string
  onValueChange: (value: string) => void
  onCollapse: () => void
  onExpand: () => void
}

/**
 * The side column cell. Card and rail are both laid in it so they can
 * cross-fade while the track animates; the hidden one is `inert`. On collapse
 * the card keeps the width it had (frozen), so it fades out clipped instead of
 * reflowing on every frame of the shrinking track. Once faded out it unmounts,
 * so its tabs are not mounted twice while the rail's floating panels are in use
 * (with reduced motion it simply stays mounted, hidden).
 */
function RecordCollaborationSide({
  tabs,
  collapsed,
  value,
  onValueChange,
  onCollapse,
  onExpand,
}: RecordCollaborationSideProps) {
  const cellRef = useRef<HTMLDivElement>(null)
  const [cardMounted, setCardMounted] = useState(!collapsed)
  const [frozenWidth, setFrozenWidth] = useState<number | null>(null)
  if (!collapsed && !cardMounted) {
    setCardMounted(true)
  }
  const cardStyle: CSSProperties | undefined = collapsed && frozenWidth ? { width: frozenWidth } : undefined

  const collapse = () => {
    setFrozenWidth(cellRef.current?.getBoundingClientRect().width ?? null)
    onCollapse()
  }

  const expand = () => {
    setFrozenWidth(null)
    onExpand()
  }

  return (
    // `self-stretch`: the cell spans the whole row so the rail can stay sticky;
    // `overflow-clip` (not hidden) clips the animating card without becoming a scroller.
    <div ref={cellRef} className="relative min-w-0 self-stretch overflow-clip [overflow-clip-margin:0.5rem]">
      <div
        className={cn(RECORD_COLUMN_CLASS, FADE_TRANSITION_CLASS, collapsed && HIDDEN_LAYER_CLASS)}
        style={cardStyle}
        inert={collapsed}
        onTransitionEnd={(event) => {
          if (collapsed && event.target === event.currentTarget) {
            setCardMounted(false)
          }
        }}
      >
        {cardMounted ? (
          <RecordCollaborationCard tabs={tabs} value={value} onValueChange={onValueChange} onCollapse={collapse} />
        ) : null}
      </div>
      <div className={cn('sticky top-0', FADE_TRANSITION_CLASS, !collapsed && HIDDEN_LAYER_CLASS)} inert={!collapsed}>
        <RecordCollaborationRail tabs={tabs} onExpand={expand} />
      </div>
    </div>
  )
}

/**
 * Wraps the record's sections inside its card. In the wide layout it renders
 * them as they are; in the narrow one they become the first tab ("Dettagli")
 * of a strip that also carries the collaboration tabs.
 */
export function RecordDetailsTabs({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const inline = useContext(InlineTabsContext)

  if (!inline) {
    return children
  }

  return (
    <Tabs value={inline.value} onValueChange={inline.onValueChange} className="gap-0">
      <div className="border-b px-4 py-3">
        <TabsList>
          <TabsTrigger value={DETAILS_TAB} className={RECORD_TAB_TRIGGER_CLASS}>
            <FileText className="size-3.5" aria-hidden="true" />
            {t('common.recordSide.details')}
          </TabsTrigger>
          {inline.tabs.map((tab) => (
            <TabsTrigger key={tab.value} value={tab.value} className={RECORD_TAB_TRIGGER_CLASS}>
              {tab.icon}
              {tab.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </div>
      <TabsContent value={DETAILS_TAB}>{children}</TabsContent>
      {inline.tabs.map((tab) => (
        <TabsContent key={tab.value} value={tab.value} className="min-w-0 p-4">
          {tab.content}
        </TabsContent>
      ))}
    </Tabs>
  )
}
