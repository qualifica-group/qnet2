import { useEffect, useRef } from 'react'
import { ChevronRight } from 'lucide-react'
import { ApiEndpointDetail } from '@/features/api-integrations/components/api-endpoint-detail'
import { ApiPathText } from '@/features/api-integrations/components/api-path-text'
import { MethodBadge } from '@/features/api-integrations/components/method-badge'
import type { ParsedOperation } from '@/features/api-integrations/openapi-operations'
import { cn } from '@/lib/utils'

interface ApiEndpointRowProps {
  operation: ParsedOperation
  baseUrl: string
  isOpen: boolean
  onToggle: (id: string) => void
  /** Scrolls the row into view once, when the page was opened on its deep link. */
  scrollIntoViewOnMount: boolean
}

/** One dense endpoint line; the button expands the detail inline. */
export function ApiEndpointRow({ operation, baseUrl, isOpen, onToggle, scrollIntoViewOnMount }: ApiEndpointRowProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const detailId = `endpoint-${operation.id.replace(/[^\w-]/g, '_')}`

  useEffect(() => {
    if (scrollIntoViewOnMount) {
      containerRef.current?.scrollIntoView?.({ block: 'start' })
    }
  }, [scrollIntoViewOnMount])

  return (
    <div ref={containerRef} className="scroll-mt-3 min-w-0 rounded-md border border-border bg-card">
      <button
        type="button"
        aria-expanded={isOpen}
        aria-controls={detailId}
        onClick={() => onToggle(operation.id)}
        className={cn(
          'flex w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-md px-2.5 py-1.5 text-left transition-colors hover:bg-muted/40',
          'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
        )}
      >
        <ChevronRight
          className={cn('size-3.5 shrink-0 text-muted-foreground transition-transform', isOpen && 'rotate-90')}
          aria-hidden
        />
        <MethodBadge method={operation.method} />
        <ApiPathText path={operation.path} />
        {operation.summary ? (
          <span className="min-w-0 flex-1 truncate text-xs text-muted-foreground">{operation.summary}</span>
        ) : null}
      </button>
      {isOpen ? (
        <div id={detailId}>
          <ApiEndpointDetail operation={operation} baseUrl={baseUrl} />
        </div>
      ) : null}
    </div>
  )
}
