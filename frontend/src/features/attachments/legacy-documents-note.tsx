import { useTranslation } from 'react-i18next'
import { ExternalLink } from 'lucide-react'
import { safeUrl } from '@/lib/safe-url'

/** The legacy page opens in the browser: only web schemes reach the DOM. */
const LEGACY_URL_PROTOCOLS = ['http:', 'https:'] as const

interface LegacyDocumentsNoteProps {
  url: string | null
}

/**
 * "Can't find a document? Check the old QNet" (spec 0343): shown in the
 * Documents section of a record migrated from the legacy QNet, linking to
 * that record (or its list) there. Renders nothing for a QNet record.
 */
export function LegacyDocumentsNote({ url }: LegacyDocumentsNoteProps) {
  const { t } = useTranslation()
  const href = safeUrl(url, LEGACY_URL_PROTOCOLS)

  if (href === undefined) {
    return null
  }

  return (
    <p className="flex flex-wrap items-center gap-x-1 text-xs text-muted-foreground">
      <span>{t('attachments.legacyNote')}</span>
      <a
        href={href}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex items-center gap-1 rounded-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
      >
        {t('attachments.legacyLink')}
        <ExternalLink className="size-3.5" aria-hidden="true" />
        <span className="sr-only">{t('attachments.legacyLinkNewTab')}</span>
      </a>
    </p>
  )
}
