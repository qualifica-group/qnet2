import { Skeleton } from '@/components/ui/skeleton'
import { ApiDocsStatus } from '@/features/api-integrations/components/api-docs-status'
import { ApiReference } from '@/features/api-integrations/components/api-reference'
import { ApiReferenceHeader } from '@/features/api-integrations/components/api-reference-header'
import { useOpenApiDocument } from '@/features/api-integrations/use-openapi-document'
import { useNavigation } from '@/features/navigation/use-navigation'
import type { NavigationItem } from '@/features/navigation/types'

/** Stable reference while the menu is loading, so the module memo is not recomputed. */
const NO_NAVIGATION: NavigationItem[] = []

/** API reference: everything is read from the OpenAPI document served by the backend. */
export function ApiDocsContent() {
  const { document, isLoading, isGenerating, isError, retry } = useOpenApiDocument()
  const { data: navigation } = useNavigation()

  if (isLoading) {
    return (
      <div className="flex flex-col gap-3" aria-busy="true">
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (document) {
    return <ApiReference document={document} navigation={navigation ?? NO_NAVIGATION} />
  }

  // The header (downloads included) stays visible while there is no document yet
  return (
    <div className="flex min-w-0 flex-col gap-3">
      <ApiReferenceHeader baseUrl={null} version={null} endpointCount={null} moduleCount={null} />
      <ApiDocsStatus failed={isError || !isGenerating} onRetry={retry} />
    </div>
  )
}
