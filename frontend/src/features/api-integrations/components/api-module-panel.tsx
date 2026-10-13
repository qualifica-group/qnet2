import { useTranslation } from 'react-i18next'
import type { ApiModule } from '@/features/api-integrations/api-modules'
import { moduleLabel } from '@/features/api-integrations/api-module-labels'
import { ModuleIcon } from '@/features/api-integrations/components/module-icon'
import { ApiEndpointRow } from '@/features/api-integrations/components/api-endpoint-row'

interface ApiModulePanelProps {
  module: ApiModule
  baseUrl: string
  openOperationId: string | null
  /** Operation the page was opened on through the URL hash. */
  initialOperationId: string | null
  onToggleOperation: (id: string) => void
}

/** A module of the API: icon, name, base path, endpoint count and its dense endpoint list. */
export function ApiModulePanel({
  module,
  baseUrl,
  openOperationId,
  initialOperationId,
  onToggleOperation,
}: ApiModulePanelProps) {
  const { t } = useTranslation()
  const headingId = `api-module-${module.key}`

  return (
    <section aria-labelledby={headingId} className="flex min-w-0 flex-col gap-2">
      <header className="flex flex-wrap items-center gap-2">
        <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-card shadow-xs">
          <ModuleIcon module={module} className="size-4 text-primary" />
        </span>
        <h3 id={headingId} className="text-base font-semibold">
          {moduleLabel(t, module)}
        </h3>
        <code className="rounded bg-surface px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
          {module.basePath}
        </code>
        <span className="text-xs text-muted-foreground">
          {t('apiIntegrations.docs.endpointsCount', { count: module.operations.length })}
        </span>
      </header>
      <div className="flex min-w-0 flex-col gap-1.5">
        {module.operations.map((operation) => (
          <ApiEndpointRow
            key={operation.id}
            operation={operation}
            baseUrl={baseUrl}
            isOpen={openOperationId === operation.id}
            onToggle={onToggleOperation}
            scrollIntoViewOnMount={initialOperationId === operation.id}
          />
        ))}
      </div>
    </section>
  )
}
