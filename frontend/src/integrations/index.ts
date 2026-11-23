import type { RouteObject } from 'react-router-dom'
import { formalabIntegration } from '@/integrations/formalab/definition'
import type { IntegrationDefinition } from '@/integrations/types'

/** Every client-specific integration known to this build; each stays dormant until the backend lists it as active. */
export const INTEGRATIONS: readonly IntegrationDefinition[] = [formalabIntegration]

export const integrationDevRoutes: RouteObject[] = INTEGRATIONS.flatMap((integration) => integration.devRoutes ?? [])
