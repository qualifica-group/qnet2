import { vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { ApiDocsContent } from '@/features/api-integrations/components/api-docs-content'
import { createWrapper } from '@/features/api-integrations/test-support'
import type { OpenApiDocument } from '@/features/api-integrations/openapi-types'
import type { ApiDocsStatus } from '@/features/api-integrations/types'
import type { NavigationItem } from '@/features/navigation/types'

export const document: OpenApiDocument = {
  openapi: '3.1.0',
  info: { version: '1.0' },
  servers: [{ url: 'https://qnet.test/api' }],
  tags: [{ name: 'leads', description: 'Prospective customers (Lead) collected by marketing campaigns.' }],
  paths: {
    '/leads': {
      post: {
        operationId: 'lead.store',
        tags: ['Lead'],
        summary: 'POST /api/leads — create a lead',
        requestBody: { content: { 'application/json': { schema: { $ref: '#/components/schemas/StoreLead' } } } },
        responses: { '201': { content: { 'application/json': { schema: { type: 'object', properties: { id: { type: 'integer' } } } } } } },
      },
      get: {
        operationId: 'lead.index',
        tags: ['Lead'],
        summary: 'List leads',
        parameters: [{ name: 'per_page', in: 'query', required: false, description: 'Page size', schema: { type: 'integer' } }],
        responses: { '200': {} },
      },
    },
    '/leads/{lead}': { delete: { operationId: 'lead.destroy', tags: ['Lead'], summary: 'Delete a lead', responses: { '204': {} } } },
    '/sources': { get: { operationId: 'source.index', tags: ['Source'], summary: 'List sources', responses: {} } },
    '/work-order-payment-statuses': { get: { operationId: 'wops.index', tags: ['Wops'], summary: 'List statuses', responses: {} } },
  },
  components: {
    schemas: {
      StoreLead: {
        type: 'object',
        required: ['registry_id'],
        properties: {
          registry_id: { type: 'integer', description: 'Owner registry' },
          status: { type: 'string', enum: ['open', 'won'] },
          address: { type: 'object', properties: { city: { type: 'string' } } },
        },
      },
    },
  },
}

export const menu: NavigationItem[] = [
  {
    key: 'marketing', label: 'navigation.marketingLeads', icon: null, route: null, type: 'section',
    children: [{ key: 'leads', label: 'navigation.leads', icon: 'megaphone', route: '/leads', type: 'item', children: [] }],
  },
  {
    key: 'configuration', label: 'navigation.configuration', icon: null, route: null, type: 'section',
    children: [{ key: 'sources', label: 'navigation.sources', icon: 'tag', route: '/sources', type: 'item', children: [] }],
  },
]

export const GENERATED_AT = '2026-11-13T00:00:00Z'

export const readyStatus: ApiDocsStatus = { status: 'ready', generated_at: GENERATED_AT }

export interface DocsApiMock {
  fetchOpenApiDocument: ReturnType<typeof vi.fn>
  fetchApiDocsStatus: ReturnType<typeof vi.fn>
  refreshApiDocs: ReturnType<typeof vi.fn>
  downloadApiDoc: ReturnType<typeof vi.fn>
}

export function resetDocsMocks(mocks: {
  api: DocsApiMock
  navigation: { items: NavigationItem[] }
  toast: { success: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn>; info: ReturnType<typeof vi.fn> }
  writeText: ReturnType<typeof vi.fn>
}) {
  mocks.api.fetchOpenApiDocument.mockReset().mockResolvedValue(document)
  mocks.api.fetchApiDocsStatus.mockReset().mockResolvedValue(readyStatus)
  mocks.api.refreshApiDocs.mockReset()
  mocks.api.downloadApiDoc.mockReset().mockResolvedValue(undefined)
  mocks.toast.info.mockReset()
  mocks.toast.error.mockReset()
  mocks.navigation.items = menu
  mocks.writeText.mockReset().mockResolvedValue(undefined)
  Object.defineProperty(navigator, 'clipboard', { value: { writeText: mocks.writeText }, configurable: true })
  window.history.replaceState(null, '', '/dev/api-docs')
}

export function renderContent() {
  render(<ApiDocsContent />, { wrapper: createWrapper().Wrapper })
}

export const findSidebar = () => screen.findByRole('navigation', { name: 'API modules' })

export async function openLeads() {
  renderContent()
  fireEvent.click(within(await findSidebar()).getByRole('button', { name: /^Leads/ }))
}
