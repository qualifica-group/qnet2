import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronRight } from 'lucide-react'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { ApiDocsOperation } from '@/features/api-integrations/components/api-docs-operation'
import type { OperationGroup } from '@/features/api-integrations/openapi-operations'

interface ApiDocsTagGroupProps {
  group: OperationGroup
  /** Opens the group regardless of the user's toggle (an active search). */
  forceOpen: boolean
}

/** One tag of the document: collapsed by default so hundreds of operations stay light to render. */
export function ApiDocsTagGroup({ group, forceOpen }: ApiDocsTagGroupProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const isOpen = forceOpen || open

  return (
    <Collapsible open={isOpen} onOpenChange={setOpen} className="flex flex-col gap-2">
      <CollapsibleTrigger className="flex w-full items-center gap-2 rounded-md text-left text-base font-semibold focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
        <ChevronRight
          className={`size-3.5 shrink-0 transition-transform ${isOpen ? 'rotate-90' : ''}`}
          aria-hidden
        />
        {group.tag}
        <span className="text-xs font-normal text-muted-foreground">
          {t('apiIntegrations.docs.operationsCount', { count: group.operations.length })}
        </span>
      </CollapsibleTrigger>
      <CollapsibleContent className="flex flex-col gap-2">
        {group.operations.map((operation) => (
          <ApiDocsOperation key={operation.id} operation={operation} />
        ))}
      </CollapsibleContent>
    </Collapsible>
  )
}
