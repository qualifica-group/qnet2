import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'

interface ProductLinesHintsProps {
  /** Spec 0077 AC-047: adds the reason `single` roots are disabled beside other rows. */
  singleRootsBlocked: boolean
  /** Lines the hosting form adds about its own record (what the categories decide there). */
  extraHints: string[]
}

/**
 * The product-line editor's guidance as ONE compact box (user request
 * 2026-11-18, "sistema graficamente queste frasi"): an info glyph and a short
 * bulleted list instead of loose grey paragraphs stacked under the rows. A
 * tint over the hosting card (ui-design §1-bis), never a surface of its own.
 */
export function ProductLinesHints({ singleRootsBlocked, extraHints }: ProductLinesHintsProps) {
  const { t } = useTranslation()
  const hints = [
    t('productLines.hint'),
    ...(singleRootsBlocked ? [t('productLines.singleRootBlocked')] : []),
    ...extraHints,
  ]

  return (
    <div className="flex items-start gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
      <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
      <ul className="flex min-w-0 list-disc flex-col gap-1 pl-3.5 marker:text-muted-foreground/60">
        {hints.map((hint) => (
          <li key={hint} className="text-pretty">
            {hint}
          </li>
        ))}
      </ul>
    </div>
  )
}
