import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronRight } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { ApiDocsParamsTable } from '@/features/api-integrations/components/api-docs-params-table'
import { JsonBlock } from '@/features/api-integrations/components/json-block'
import type { ParsedOperation } from '@/features/api-integrations/openapi-operations'

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive'

const METHOD_VARIANTS: Record<string, BadgeVariant> = {
  GET: 'secondary',
  POST: 'default',
  PUT: 'outline',
  PATCH: 'outline',
  DELETE: 'destructive',
}

interface ApiDocsOperationProps {
  operation: ParsedOperation
}

/** One operation: always-visible summary line, expandable parameters and schemas. */
export function ApiDocsOperation({ operation }: ApiDocsOperationProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const label = `${operation.method} ${operation.path}`

  return (
    <Collapsible open={open} onOpenChange={setOpen} className="rounded-md border border-border bg-card">
      <CollapsibleTrigger
        aria-label={t('apiIntegrations.docs.toggle', { operation: label })}
        className="flex w-full flex-wrap items-center gap-2 px-3 py-2 text-left text-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        <ChevronRight
          className={`size-3.5 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`}
          aria-hidden
        />
        <Badge variant={METHOD_VARIANTS[operation.method] ?? 'outline'}>{operation.method}</Badge>
        <code className="min-w-0 break-all text-xs">{operation.path}</code>
        {operation.summary ? (
          <span className="min-w-0 truncate text-xs text-muted-foreground">{operation.summary}</span>
        ) : null}
      </CollapsibleTrigger>
      <CollapsibleContent className="flex flex-col gap-3 border-t border-border p-3">
        {operation.description ? <p className="text-sm">{operation.description}</p> : null}
        {operation.parameters.length > 0 ? (
          <div className="flex flex-col gap-1.5">
            <h4 className="text-xs font-semibold">{t('apiIntegrations.docs.parameters')}</h4>
            <ApiDocsParamsTable parameters={operation.parameters} />
          </div>
        ) : null}
        {operation.requestSchema ? (
          <div className="flex flex-col gap-1.5">
            <h4 className="text-xs font-semibold">{t('apiIntegrations.docs.request')}</h4>
            <JsonBlock label={t('apiIntegrations.docs.request')} value={operation.requestSchema} />
          </div>
        ) : null}
        {operation.responseSchema ? (
          <div className="flex flex-col gap-1.5">
            <h4 className="text-xs font-semibold">
              {t('apiIntegrations.docs.response', { status: operation.responseStatus })}
            </h4>
            <JsonBlock
              label={t('apiIntegrations.docs.response', { status: operation.responseStatus })}
              value={operation.responseSchema}
            />
          </div>
        ) : null}
      </CollapsibleContent>
    </Collapsible>
  )
}
