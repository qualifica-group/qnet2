import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/components/ui/skeleton'
import { useHelpGuide } from '@/features/help/hooks/use-help-guide'
import { HelpBlockRenderer } from '@/features/help/components/help-block-renderer'
import { helpSectionDomId } from '@/features/help/help-dom'
import type { HelpLocale } from '@/features/help/help-content-loader'

interface HelpGuideViewProps {
  locale: HelpLocale
  guideKey: string
  /** Section to scroll into view once the guide has loaded (search result click). */
  scrollToSectionId: string | null
  onScrolled: () => void
}

/**
 * Displays one guide: title, summary, sections (AC-002/AC-006), with a
 * loading skeleton and an "unavailable" state when the content file for this
 * key hasn't been authored yet (AC-012).
 */
export function HelpGuideView({ locale, guideKey, scrollToSectionId, onScrolled }: HelpGuideViewProps) {
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

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h3 className="text-base font-semibold text-foreground">{guide.title}</h3>
        <p className="text-sm text-muted-foreground">{guide.summary}</p>
      </div>
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
