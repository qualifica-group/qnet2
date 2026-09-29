import type { CSSProperties } from 'react'
import { Lock, SearchX, type LucideIcon } from 'lucide-react'
import type { RecordUnavailableReason } from '@/lib/record-unavailable-reason'

// The brand mark painted with the theme's primary color (same treatment as
// the q-net error pages), so it follows light/dark without a second asset.
const LOGO_MASK_STYLE: CSSProperties = {
  WebkitMask: 'url(/brands/logo.svg) center / contain no-repeat',
  mask: 'url(/brands/logo.svg) center / contain no-repeat',
}

// The badge tells the two cases apart at a glance, not only by the title.
const REASON_ICON: Record<RecordUnavailableReason, LucideIcon> = {
  notFound: SearchX,
  forbidden: Lock,
}

interface RecordUnavailableProps {
  reason: RecordUnavailableReason
  title: string
  description: string
}

/**
 * Full-area state shown in place of a record's detail when the server answers
 * 404 (does not exist) or 403 (not visible to the actor). No retry action:
 * both answers are final, unlike a network failure.
 */
export function RecordUnavailable({ reason, title, description }: RecordUnavailableProps) {
  const Icon = REASON_ICON[reason]

  return (
    <div className="flex flex-1 flex-col items-center justify-center bg-card p-8 text-center">
      <div aria-hidden className="relative mb-6">
        <div className="size-16 bg-primary" style={LOGO_MASK_STYLE} />
        <span className="absolute -right-2 -bottom-2 flex size-7 items-center justify-center rounded-full border bg-card text-muted-foreground shadow-sm">
          <Icon className="size-3.5" />
        </span>
      </div>
      <h2 className="mb-2 text-xl font-semibold">{title}</h2>
      <p className="max-w-md text-sm text-muted-foreground">{description}</p>
    </div>
  )
}
