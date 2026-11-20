/** Query keys of the API integrations feature (the client list lives in the generic table framework). */
export const apiIntegrationsKeys = {
  all: ['api-integrations'] as const,
  detail: (id: number) => [...apiIntegrationsKeys.all, 'detail', id] as const,
  docs: () => [...apiIntegrationsKeys.all, 'docs'] as const,
  docsStatus: () => [...apiIntegrationsKeys.docs(), 'status'] as const,
  /** Keyed by generation instant: a regenerated document is a new cache entry. */
  openApi: (generatedAt: string) => [...apiIntegrationsKeys.docs(), 'openapi', generatedAt] as const,
}
