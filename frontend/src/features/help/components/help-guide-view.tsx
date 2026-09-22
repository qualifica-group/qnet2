import { createElement, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/components/ui/skeleton'
import { useHelpGuide } from '@/features/help/hooks/use-help-guide'
import { HelpBlockRenderer } from '@/features/help/components/help-block-renderer'
import { helpSectionDomId } from '@/features/help/help-dom'
import { resolveHelpGuideIcon } from '@/features/help/help-icons'
import type { HelpLocale } from '@/features/help/help-content-loader'
import type { HelpSection } from '@/features/help/types'

interface HelpGuideViewProps {
  locale: HelpLocale
  guideKey: string
  /** This guide's own menu icon name, or `null` (AC-017). */
  guideIcon: string | null
  /** Section to scroll into view once the guide has loaded (search result click). */
  scrollToSectionId: string | null
  onScrolled: () => void
}

/**
 * Displays one guide: icon, title, summary, a clickable section index and
 * the sections themselves (AC-002/AC-006/AC-017), with a loading skeleton
 * and an "unavailable" state when the content file hasn't been authored yet
 * (AC-012).
 */
export function HelpGuideView({ locale, guideKey, guideIcon, scrollToSectionId, onScrolled }: HelpGuideViewProps) {
  const { t } = useTranslation()
  const guideQuery = useHelpGuide(locale, guideKey)
  const guide = guideQuery.data

  useEffect(() => {
    if (!scrollToSectionId || !guide) {
      return
    }
    document.getElementById(helpSectionDomId(guideKey, scrollToSectionId))?.scrollIntoView({ block: 'start' })
    onScrolled()
  }, [scrollToSectionId, guide, guideKey, onScrolled])

  if (guideQuery.isLoading) {
    return (
      <div className="flex flex-col gap-2">
        <span className="sr-only" role="status">
          {t('common.loading')}
        </span>
        <div aria-hidden="true" className="flex flex-col gap-2">
          <Skeleton className="h-5 w-2/3" />
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-24 w-full" />
        </div>
      </div>
    )
  }

  if (!guide) {
    return (
      <p className="text-sm text-muted-foreground" role="status">
        {t('help.guideUnavailable')}
      </p>
    )
  }

  const icon = createElement(resolveHelpGuideIcon(guideKey, guideIcon), {
    className: 'mt-0.5 size-4 shrink-0 text-muted-foreground',
    'aria-hidden': true,
  })

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-start gap-2">
        {icon}
        <div className="flex flex-col gap-1">
          <h3 className="text-base font-semibold text-foreground">{guide.title}</h3>
          <p className="text-sm text-muted-foreground">{guide.summary}</p>
        </div>
      </div>

      <HelpGuideSectionsIndex guideKey={guideKey} sections={guide.sections} />

      {guide.sections.map((section) => (
        <section
          key={section.id}
          id={helpSectionDomId(guideKey, section.id)}
          className="flex flex-col gap-2 scroll-mt-2"
        >
          <h4 className="text-sm font-semibold text-foreground">{section.title}</h4>
          {section.blocks.map((block, index) => (
            <HelpBlockRenderer key={index} block={block} />
          ))}
        </section>
      ))}
    </div>
  )
}

/** Compact chip list scrolling the guide to a section on click (AC-017). */
function HelpGuideSectionsIndex({ guideKey, sections }: { guideKey: string; sections: HelpSection[] }) {
  const { t } = useTranslation()

  if (sections.length < 2) {
    return null
  }

  return (
    <ul aria-label={t('help.sectionsIndexLabel')} className="flex flex-wrap gap-1.5">
      {sections.map((section) => (
        <li key={section.id}>
          <button
            type="button"
            onClick={() =>
              document.getElementById(helpSectionDomId(guideKey, section.id))?.scrollIntoView({ block: 'start' })
            }
            className="rounded-full border border-border bg-surface px-2.5 py-1 text-xs text-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
          >
            {section.title}
          </button>
        </li>
      ))}
    </ul>
  )
}
