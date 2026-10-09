import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ApiDocsTab } from '@/features/api-integrations/components/api-docs-tab'
import { createWrapper } from '@/features/api-integrations/test-support'
import type { OpenApiDocument } from '@/features/api-integrations/openapi-types'

const api = vi.hoisted(() => ({ fetchOpenApiDocument: vi.fn(), downloadApiDoc: vi.fn() }))
vi.mock('@/features/api-integrations/api', () => api)
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const document: OpenApiDocument = {
  openapi: '3.1.0',
  servers: [{ url: 'https://qnet.test/api' }],
  paths: {
    '/leads': {
      get: {
        tags: ['Leads'],
        summary: 'List leads',
        parameters: [{ name: 'per_page', in: 'query', required: false, schema: { type: 'integer' } }],
        responses: {
          '200': { content: { 'application/json': { schema: { $ref: '#/components/schemas/LeadList' } } } },
        },
      },
      post: {
        tags: ['Leads'],
        summary: 'Create lead',
        requestBody: { content: { 'application/json': { schema: { type: 'object', required: ['registry_id'] } } } },
        responses: { '201': {} },
      },
    },
    '/sources': { get: { tags: ['Lookups'], summary: 'List sources', responses: {} } },
  },
  components: { schemas: { LeadList: { type: 'object', properties: { total: { type: 'integer' } } } } },
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  api.fetchOpenApiDocument.mockReset().mockResolvedValue(document)
  api.downloadApiDoc.mockReset().mockResolvedValue(undefined)
})

function renderTab() {
  render(<ApiDocsTab />, { wrapper: createWrapper().Wrapper })
}

describe('ApiDocsTab (AC-023)', () => {
  it('lists the tags collapsed by default and opens one on click', async () => {
    renderTab()

    const leads = await screen.findByRole('button', { name: /Leads/ })
    expect(screen.getByRole('button', { name: /Lookups/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Show details of GET /leads' })).not.toBeInTheDocument()

    fireEvent.click(leads)
    expect(screen.getByRole('button', { name: 'Show details of GET /leads' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Show details of POST /leads' })).toBeInTheDocument()
  })

  it('filters by path, summary or tag and opens the matching groups', async () => {
    renderTab()

    fireEvent.change(await screen.findByRole('searchbox', { name: 'Search operations' }), {
      target: { value: 'sources' },
    })
    expect(screen.getByRole('button', { name: 'Show details of GET /sources' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Leads/ })).not.toBeInTheDocument()

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzz' } })
    expect(screen.getByText('No operation matches the search.')).toBeInTheDocument()
  })

  it('expands an operation with its parameters and response schema', async () => {
    renderTab()

    fireEvent.click(await screen.findByRole('button', { name: /Leads/ }))
    fireEvent.click(await screen.findByRole('button', { name: 'Show details of GET /leads' }))

    const table = await screen.findByRole('table', { name: 'Parameters' })
    expect(within(table).getByText('per_page')).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Response 200' })).toHaveTextContent('"total"')
  })

  it('documents the client key mode with its curl (AC-013)', async () => {
    renderTab()

    expect(await screen.findByRole('heading', { name: 'Authentication' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Client key' })).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Base URL' })).toHaveTextContent('https://qnet.test/api')
    expect(screen.getByRole('region', { name: 'Header' })).toHaveTextContent('Authorization: Bearer <your-api-key>')
    expect(screen.getByRole('region', { name: 'Example call' })).toHaveTextContent('https://qnet.test/api/leads/1')
  })

  it('documents the user login mode with the client-login and logout curls (AC-013)', async () => {
    renderTab()

    expect(await screen.findByRole('heading', { name: 'User login' })).toBeInTheDocument()
    const login = screen.getByRole('region', { name: 'User login (POST /auth/client-login)' })
    expect(login).toHaveTextContent('https://qnet.test/api/auth/client-login')
    expect(login).toHaveTextContent('Authorization: Bearer <your-api-key>')
    expect(login).toHaveTextContent('"email"')
    expect(screen.getByRole('region', { name: 'Call with the user token' })).toHaveTextContent(
      'Authorization: Bearer <user-token>',
    )
    expect(screen.getByRole('region', { name: 'Logout (POST /auth/logout)' })).toHaveTextContent(
      'https://qnet.test/api/auth/logout',
    )
  })

  it('states that the APIs may change and the docs update themselves', async () => {
    renderTab()
    expect(await screen.findByText(/may change with updates/)).toBeInTheDocument()
  })

  it('downloads the OpenAPI file and the Postman collection', async () => {
    renderTab()

    fireEvent.click(await screen.findByRole('button', { name: 'Download OpenAPI' }))
    await vi.waitFor(() => expect(api.downloadApiDoc).toHaveBeenCalledWith('openapi'))
    fireEvent.click(screen.getByRole('button', { name: 'Download Postman collection' }))
    await vi.waitFor(() => expect(api.downloadApiDoc).toHaveBeenCalledWith('postman'))
  })

  it('shows an error when the document cannot be loaded', async () => {
    api.fetchOpenApiDocument.mockRejectedValue(new Error('boom'))
    renderTab()
    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the API documentation.')
  })
})
