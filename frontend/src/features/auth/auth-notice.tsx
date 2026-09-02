import type { ReactNode } from 'react'
import { AlertCircle, CheckCircle2 } from 'lucide-react'
import { cn } from '@/lib/utils'

type NoticeTone = 'error' | 'success'

/**
 * Standalone outcome banner for the authentication screens: a rejected login,
 * an expired reset link, a reset mail on its way.
 *
 * The message is set in `--foreground` rather than in the tone colour. Measured
 * against the surfaces it sits on, `--destructive` reaches only 2.34:1 in light
 * and 1.99:1 in dark, both well under AA, so tinting the copy with it would
 * make the one string the user must read the hardest one on the page. The tone
 * stays legible through the wash, the rule, the icon and the ARIA role, so
 * colour is never the only carrier of the state.
 */
export function AuthNotice({ tone, children }: { tone: NoticeTone; children: ReactNode }) {
  const isError = tone === 'error'
  const Icon = isError ? AlertCircle : CheckCircle2

  return (
    <p
      role={isError ? 'alert' : 'status'}
      className={cn(
        'flex items-start gap-2 rounded-md border border-l-2 px-3 py-2 text-sm font-medium text-foreground',
        isError
          ? 'border-destructive/40 border-l-destructive bg-destructive/10'
          : 'border-success/40 border-l-success bg-success/10',
      )}
    >
      <Icon
        className={cn('mt-0.5 size-4 shrink-0', isError ? 'text-destructive' : 'text-success')}
        aria-hidden
      />
      <span>{children}</span>
    </p>
  )
}
