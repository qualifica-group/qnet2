import { createElement } from 'react'
import { ChevronRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { resolveIcon } from '@/features/navigation/icon-map'
import { resolveHelpGuideIcon } from '@/features/help/help-icons'
import { groupConsecutiveHelpGuides } from '@/features/help/help-visible-guides'
import type { HelpGuideGroup, HelpVisibleGuide } from '@/features/help/help-visible-guides'

interface HelpGuideIndexProps {
  guides: readonly HelpVisibleGuide[]
  /** The module guide for the page the user is on, marked "Sei qui" (AC-016). */
  currentGuideKey: string
  onSelect: (key: string) => void
}

/**
 * Indexed guides (AC-004/AC-014/AC-016): top-level entries (incl. "general",
 * always first) render flat; menu groups render as collapsible sections with
 * an icon, a guide count, and open by default only when they hold the
 * current module — the rest start collapsed to keep the list scannable.
 */
export function HelpGuideIndex({ guides, currentGuideKey, onSelect }: HelpGuideIndexProps) {
  const { t } = useTranslation()
  const groups = groupConsecutiveHelpGuides(guides)

  return (
    <nav aria-label={t('help.indexTitle')} className="flex flex-col gap-3">
      {groups.map((group, groupIndex) =>
        group.label === null ? (
          <ul key={groupIndex} className="flex flex-col gap-0.5">
            {group.guides.map((guide) => (
              <HelpGuideIndexItem
                key={guide.key}
                guide={guide}
                isCurrent={guide.key === currentGuideKey}
                onSelect={onSelect}
              />
            ))}
          </ul>
        ) : (
          <HelpGuideIndexGroup
            key={groupIndex}
            group={group}
            currentGuideKey={currentGuideKey}
            onSelect={onSelect}
          />
        ),
      )}
    </nav>
  )
}

function HelpGuideIndexGroup({
  group,
  currentGuideKey,
  onSelect,
}: {
  group: HelpGuideGroup
  currentGuideKey: string
  onSelect: (key: string) => void
}) {
  const hasCurrent = group.guides.some((guide) => guide.key === currentGuideKey)
  const groupIcon = group.icon
    ? createElement(resolveIcon(group.icon), { className: 'size-3.5 shrink-0', 'aria-hidden': true })
    : null

  return (
    <Collapsible defaultOpen={hasCurrent} className="group">
      <CollapsibleTrigger asChild>
        <button
          type="button"
          className="flex w-full items-center gap-1.5 rounded-md px-1 py-1 text-xs font-medium tracking-wide text-muted-foreground uppercase hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
        >
          {groupIcon}
          <span className="flex-1 text-left">{group.label}</span>
          <span aria-hidden="true">{group.guides.length}</span>
          <ChevronRight
            className="size-3.5 shrink-0 transition-transform group-data-[state=open]:rotate-90"
            aria-hidden="true"
          />
        </button>
      </CollapsibleTrigger>
      <CollapsibleContent>
        <ul className="flex flex-col gap-0.5 pt-1">
          {group.guides.map((guide) => (
            <HelpGuideIndexItem
              key={guide.key}
              guide={guide}
              isCurrent={guide.key === currentGuideKey}
              onSelect={onSelect}
            />
          ))}
        </ul>
      </CollapsibleContent>
    </Collapsible>
  )
}

function HelpGuideIndexItem({
  guide,
  isCurrent,
  onSelect,
}: {
  guide: HelpVisibleGuide
  isCurrent: boolean
  onSelect: (key: string) => void
}) {
  const { t } = useTranslation()
  const icon = createElement(resolveHelpGuideIcon(guide.key, guide.icon), {
    className: 'size-4 shrink-0 text-muted-foreground',
    'aria-hidden': true,
  })

  return (
    <li>
      <button
        type="button"
        onClick={() => onSelect(guide.key)}
        aria-current={isCurrent ? 'page' : undefined}
        className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        {icon}
        <span className="flex-1 truncate">{guide.label}</span>
        {isCurrent ? (
          <>
            {' '}
            <Badge variant="secondary" className="shrink-0">
              {t('help.youAreHere')}
            </Badge>
          </>
        ) : null}
      </button>
    </li>
  )
}
