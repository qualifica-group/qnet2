/** Query keys of the API integrations feature (the client list lives in the generic table framework). */
export const apiIntegrationsKeys = {
  all: ['api-integrations'] as const,
  detail: (id: number) => [...apiIntegrationsKeys.all, 'detail', id] as const,
  openApi: () => [...apiIntegrationsKeys.all, 'openapi'] as const,
}
