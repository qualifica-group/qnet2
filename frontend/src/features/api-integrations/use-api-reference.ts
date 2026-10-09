import { useMemo, useState } from 'react'
import {
  buildApiModules,
  groupModulesBySection,
  type ApiModule,
  type ApiModuleSection,
} from '@/features/api-integrations/api-modules'
import {
  API_METHODS,
  filterOperations,
  matchesQuery,
  parseOpenApiOperations,
  type ApiMethod,
  type ParsedOperation,
} from '@/features/api-integrations/openapi-operations'
import type { OpenApiDocument } from '@/features/api-integrations/openapi-types'
import type { NavigationItem } from '@/features/navigation/types'

/** Non-module views; the colon keeps them apart from any module key (a path segment). */
export const INTRO_VIEW = ':intro'
export const ALL_RESULTS_VIEW = ':all'

/** Fallback when the document declares no server (should not happen: spec fixes `servers[0].url`). */
const DEFAULT_BASE_URL = '/api'
const NO_NAVIGATION: NavigationItem[] = []

export interface ApiReferenceState {
  baseUrl: string
  version: string | null
  endpointCount: number
  moduleCount: number
  query: string
  setQuery: (query: string) => void
  methods: ReadonlySet<ApiMethod>
  toggleMethod: (method: ApiMethod) => void
  methodCounts: Record<ApiMethod, number>
  isFiltering: boolean
  /** Sections with only the modules (and operations) matching the filters. */
  sections: ApiModuleSection[]
  /** Modules rendered in the main panel: one, or all matches for the results view. */
  panelModules: ApiModule[]
  view: string
  selectView: (view: string) => void
  openOperationId: string | null
  /** Operation named by the hash of the opening URL, if any. */
  initialOperationId: string | null
  toggleOperation: (id: string) => void
}

/** Absolute URL of the page with the hash that deep-links an operation. */
export function buildOperationLink(operationId: string): string {
  return `${window.location.origin}${window.location.pathname}#${encodeURIComponent(operationId)}`
}

function writeHash(operationId: string | null) {
  const { pathname, search } = window.location
  const hash = operationId === null ? '' : `#${encodeURIComponent(operationId)}`
  window.history.replaceState(window.history.state, '', `${pathname}${search}${hash}`)
}

function readHashOperation(operations: ParsedOperation[]): ParsedOperation | null {
  const id = decodeURIComponent(window.location.hash.slice(1))
  return id === '' ? null : (operations.find((operation) => operation.id === id) ?? null)
}

function moduleKeyOfOperation(modules: ApiModule[], operationId: string): string | undefined {
  return modules.find((module) => module.operations.some((operation) => operation.id === operationId))?.key
}

/** Selection, filters and deep-link state of the API reference; the parsing is memoized per document. */
export function useApiReference(
  document: OpenApiDocument,
  navigation: NavigationItem[] = NO_NAVIGATION,
): ApiReferenceState {
  const operations = useMemo(() => parseOpenApiOperations(document), [document])
  const modules = useMemo(() => buildApiModules(operations, navigation), [operations, navigation])

  // Step 1: the hash of the opening URL selects the module and opens the endpoint
  const [initial] = useState(() => {
    const target = readHashOperation(operations)
    return { operationId: target?.id ?? null, view: target ? moduleKeyOfOperation(modules, target.id) : undefined }
  })
  const [view, setView] = useState(initial.view ?? INTRO_VIEW)
  const [openOperationId, setOpenOperationId] = useState(initial.operationId)
  const [query, setQuery] = useState('')
  const [methods, setMethods] = useState<ReadonlySet<ApiMethod>>(new Set())

  const isFiltering = query.trim() !== '' || methods.size > 0

  // Step 2: counts per method reflect the text query only, so chips stay usable together
  const methodCounts = useMemo(() => {
    const counts = Object.fromEntries(API_METHODS.map((method) => [method, 0])) as Record<ApiMethod, number>
    for (const operation of operations) {
      if (matchesQuery(operation, query)) {
        counts[operation.method] += 1
      }
    }
    return counts
  }, [operations, query])

  // Step 3: modules keep only the operations matching query and methods
  const visibleModules = useMemo(() => {
    if (!isFiltering) {
      return modules
    }
    return modules
      .map((module) => ({ ...module, operations: filterOperations(module.operations, { query, methods }) }))
      .filter((module) => module.operations.length > 0)
  }, [modules, isFiltering, query, methods])
  const sections = useMemo(() => groupModulesBySection(visibleModules), [visibleModules])

  // Step 4: intro, results view and a module filtered out all resolve against the current filters
  const isModuleView = visibleModules.some((module) => module.key === view)
  const activeView = isModuleView ? view : isFiltering ? ALL_RESULTS_VIEW : INTRO_VIEW
  const panelModules = useMemo(() => {
    if (activeView === ALL_RESULTS_VIEW) {
      return visibleModules
    }
    return visibleModules.filter((module) => module.key === activeView)
  }, [activeView, visibleModules])

  const selectView = (next: string) => {
    setView(next)
    setOpenOperationId(null)
    writeHash(null)
  }

  const toggleMethod = (method: ApiMethod) =>
    setMethods((current) => {
      const next = new Set(current)
      if (!next.delete(method)) {
        next.add(method)
      }
      return next
    })

  const toggleOperation = (id: string) => {
    const next = openOperationId === id ? null : id
    setOpenOperationId(next)
    writeHash(next)
  }

  return {
    baseUrl: document.servers?.[0]?.url ?? DEFAULT_BASE_URL,
    version: document.info?.version ?? null,
    endpointCount: operations.length,
    moduleCount: modules.length,
    query,
    setQuery,
    methods,
    toggleMethod,
    methodCounts,
    isFiltering,
    sections,
    panelModules,
    view: activeView,
    selectView,
    openOperationId,
    initialOperationId: initial.operationId,
    toggleOperation,
  }
}
