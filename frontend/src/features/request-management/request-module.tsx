/* eslint-disable react-refresh/only-export-components -- config/context module: the provider is colocated with the frozen module configs and hook it exists to serve (spec 0130 frontend_contract), not a component file */
import { createContext, useContext, type ReactNode } from 'react'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'

/** The frozen `enrollee-management` slug (spec 0130 D-1): no existing constant to reuse, unlike `REQUEST_MANAGEMENT_DOMAIN`. */
const ENROLLEE_MANAGEMENT_DOMAIN = 'enrollee-management'

export type RequestModuleKey = typeof REQUEST_MANAGEMENT_DOMAIN | typeof ENROLLEE_MANAGEMENT_DOMAIN

/**
 * Everything the shared request-management feature reads to behave as either
 * "Gestione Richieste" or "Gestione Iscritti" (spec 0130 frozen
 * `frontend_contract`): the two modules are two values of this config, never
 * a second copy of the feature's components/hooks.
 */
export interface RequestModuleConfig {
  /** = table domain = notes entityType = activity/FCR resource = query-key/storage-key root. */
  key: RequestModuleKey
  apiBasePath: string
  routeBasePath: string
  /** i18n key under `navigation.*`. */
  labelKey: string
  permission: (ability: string) => string
  /** D-8: Gestione Iscritti has no creation surface — an explicit flag, never derived from `can()`. */
  allowsCreate: boolean
  /**
   * D-9: `POST /api/assignment/selection-scope` has no module segment of its
   * own — its `domain` discriminant names the RECORD family, not this route
   * prefix, so it cannot be derived from `key`. `use-quote-assignment-scope.ts`
   * reads this to pick the right branch of `AssignmentScopePayload`.
   */
  assignmentDomain: 'quotes' | 'enrollees'
}

function buildModuleConfig(
  key: RequestModuleKey,
  labelKey: string,
  allowsCreate: boolean,
  assignmentDomain: RequestModuleConfig['assignmentDomain'],
): RequestModuleConfig {
  return {
    key,
    apiBasePath: `/${key}`,
    routeBasePath: `/${key}`,
    labelKey,
    permission: (ability) => `${key}.${ability}`,
    allowsCreate,
    assignmentDomain,
  }
}

export const REQUEST_MODULE: RequestModuleConfig = buildModuleConfig(
  REQUEST_MANAGEMENT_DOMAIN,
  'navigation.requestManagement',
  true,
  'quotes',
)

export const ENROLLEE_MODULE: RequestModuleConfig = buildModuleConfig(
  ENROLLEE_MANAGEMENT_DOMAIN,
  'navigation.enrolleeManagement',
  false,
  'enrollees',
)

const RequestModuleContext = createContext<RequestModuleConfig | null>(null)

export interface RequestModuleProviderProps {
  module: RequestModuleConfig
  children: ReactNode
}

/** Scopes every hook/component of this feature to one module. Unwrapped call sites keep working (default below). */
export function RequestModuleProvider({ module, children }: RequestModuleProviderProps) {
  return <RequestModuleContext.Provider value={module}>{children}</RequestModuleContext.Provider>
}

/** Default = Gestione Richieste (AC-017): every existing call site keeps its current behaviour unwrapped. */
export function useRequestModule(): RequestModuleConfig {
  return useContext(RequestModuleContext) ?? REQUEST_MODULE
}
