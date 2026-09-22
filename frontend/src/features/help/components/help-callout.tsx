import { Info, Lightbulb, TriangleAlert, type LucideIcon } from 'lucide-react'
import { renderHelpInlineText } from '@/features/help/components/help-inline-text'
import { cn } from '@/lib/utils'

type HelpCalloutTone = 'tip' | 'warning' | 'note'

const TONE_STYLE: Record<HelpCalloutTone, { icon: LucideIcon; wash: string }> = {
  tip: { icon: Lightbulb, wash: 'border-primary/40 border-l-primary bg-primary/10' },
  warning: { icon: TriangleAlert, wash: 'border-destructive/40 border-l-destructive bg-destructive/10' },
  note: { icon: Info, wash: 'border-border border-l-border bg-muted/40' },
}

/**
 * Renders `tip`/`warning`/`note` blocks (AC-008): the tone is carried by the
 * icon, the rule and the wash, but never by colour alone — `label` (i18n,
 * "Suggerimento"/"Attenzione"/"Nota") is always read aloud in the text, and
 * body copy stays `text-foreground` rather than the tone colour (measured
 * contrast rationale: see `AuthNotice`).
 */
export function HelpCallout({
  tone,
  label,
  text,
}: {
  tone: HelpCalloutTone
  label: string
  text: string
}) {
  const { icon: Icon, wash } = TONE_STYLE[tone]

  return (
    <div
      className={cn(
        'flex items-start gap-2 rounded-md border border-l-2 px-3 py-2 text-sm text-foreground',
        wash,
      )}
    >
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      <p>
        <span className="font-semibold">{label}: </span>
        {renderHelpInlineText(text)}
      </p>
    </div>
  )
}
