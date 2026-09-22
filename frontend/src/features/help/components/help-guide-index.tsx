import { useTranslation } from 'react-i18next'
import { groupConsecutiveHelpGuides } from '@/features/help/help-visible-guides'
import type { HelpVisibleGuide } from '@/features/help/help-visible-guides'

/** Indexed guides grouped by menu group label, "general" always first (AC-004). */
export function HelpGuideIndex({
  guides,
  onSelect,
}: {
  guides: readonly HelpVisibleGuide[]
  onSelect: (key: string) => void
}) {
  const { t } = useTranslation()
  const groups = groupConsecutiveHelpGuides(guides)

  return (
    <nav aria-label={t('help.indexTitle')} className="flex flex-col gap-3">
      {groups.map((group, groupIndex) => (
        <div key={groupIndex} className="flex flex-col gap-1">
          {group.label ? (
            <h5 className="px-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
              {group.label}
            </h5>
          ) : null}
          <ul className="flex flex-col gap-0.5">
            {group.guides.map((guide) => (
              <li key={guide.key}>
                <button
                  type="button"
                  onClick={() => onSelect(guide.key)}
                  className="w-full rounded-md px-2 py-1.5 text-left text-sm text-foreground hover:bg-muted/60"
                >
                  {guide.label}
                </button>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </nav>
  )
}
