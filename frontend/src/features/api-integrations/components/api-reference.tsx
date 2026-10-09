import { useTranslation } from 'react-i18next'
import { ApiModulePanel } from '@/features/api-integrations/components/api-module-panel'
import { ApiReferenceHeader } from '@/features/api-integrations/components/api-reference-header'
import { ApiReferenceIntro } from '@/features/api-integrations/components/api-reference-intro'
import { ApiReferenceSidebar } from '@/features/api-integrations/components/api-reference-sidebar'
import type { OpenApiDocument } from '@/features/api-integrations/openapi-types'
import { INTRO_VIEW, useApiReference } from '@/features/api-integrations/use-api-reference'
import type { NavigationItem } from '@/features/navigation/types'

interface ApiReferenceProps {
  document: OpenApiDocument
  navigation: NavigationItem[]
}

/** The loaded API reference: header, sticky module sidebar and the active panel. */
export function ApiReference({ document, navigation }: ApiReferenceProps) {
  const { t } = useTranslation()
  const state = useApiReference(document, navigation)

  const renderPanel = () => {
    if (state.view === INTRO_VIEW) {
      return <ApiReferenceIntro baseUrl={state.baseUrl} />
    }
    if (state.panelModules.length === 0) {
      return (
        <p className="text-sm text-muted-foreground">
          {t(state.isFiltering ? 'apiIntegrations.docs.noResults' : 'apiIntegrations.docs.empty')}
        </p>
      )
    }
    return state.panelModules.map((module) => (
      <ApiModulePanel
        key={module.key}
        module={module}
        baseUrl={state.baseUrl}
        openOperationId={state.openOperationId}
        initialOperationId={state.initialOperationId}
        onToggleOperation={state.toggleOperation}
      />
    ))
  }

  return (
    <div className="flex min-w-0 flex-col gap-3">
      <ApiReferenceHeader
        baseUrl={state.baseUrl}
        version={state.version}
        endpointCount={state.endpointCount}
        moduleCount={state.moduleCount}
      />
      <div className="flex min-w-0 flex-col gap-3 lg:flex-row lg:items-start">
        <ApiReferenceSidebar state={state} />
        <div className="flex min-w-0 flex-1 flex-col gap-4">{renderPanel()}</div>
      </div>
    </div>
  )
}
