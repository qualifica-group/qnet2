import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen } from '@testing-library/react'
import i18n from '@/i18n'
import {
  document,
  findSidebar,
  readyStatus,
  renderContent,
  resetDocsMocks,
} from '@/features/api-integrations/components/api-docs-fixtures'
import { GENERATING_POLL_MS } from '@/features/api-integrations/use-openapi-document'
import type { NavigationItem } from '@/features/navigation/types'

const api = vi.hoisted(() => ({
  fetchOpenApiDocument: vi.fn(),
  fetchApiDocsStatus: vi.fn(),
  refreshApiDocs: vi.fn(),
  downloadApiDoc: vi.fn(),
}))
const navigation = vi.hoisted(() => ({ items: [] as NavigationItem[] }))
const abilities = vi.hoisted(() => ({ granted: ['api-clients.view', 'api-clients.update'] }))
vi.mock('@/features/api-integrations/api', () => api)
vi.mock('@/features/navigation/use-navigation', () => ({ useNavigation: () => ({ data: navigation.items }) }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => abilities.granted.includes(permission), isLoading: false }),
}))
const toast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }))
vi.mock('sonner', () => ({ toast }))
const writeText = vi.fn()

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  resetDocsMocks({ api, navigation, toast, writeText })
  abilities.granted = ['api-clients.view', 'api-clients.update']
})

afterEach(() => {
  window.history.replaceState(null, '', '/')
})

describe('ApiDocsContent header', () => {
  it('shows title, base URL, version, totals and the generation instant', async () => {
    renderContent()

    expect(await screen.findByRole('heading', { name: 'API reference' })).toBeInTheDocument()
    expect(screen.getByText('https://qnet.test/api')).toBeInTheDocument()
    expect(screen.getByText('Version 1.0')).toBeInTheDocument()
    expect(screen.getByText('5 endpoints · 3 modules')).toBeInTheDocument()
    expect(screen.getByText(/^Updated on /)).toBeInTheDocument()
    expect(screen.getByText(/regenerated every night/)).toBeInTheDocument()
  })

  it('copies the base URL', async () => {
    renderContent()
    fireEvent.click(await screen.findByRole('button', { name: 'Copy base URL' }))
    await vi.waitFor(() => expect(writeText).toHaveBeenCalledWith('https://qnet.test/api'))
  })

  it('downloads the OpenAPI file and the Postman collection', async () => {
    renderContent()

    fireEvent.click(await screen.findByRole('button', { name: 'Download OpenAPI' }))
    await vi.waitFor(() => expect(api.downloadApiDoc).toHaveBeenCalledWith('openapi'))
    fireEvent.click(screen.getByRole('button', { name: 'Download Postman collection' }))
    await vi.waitFor(() => expect(api.downloadApiDoc).toHaveBeenCalledWith('postman'))
  })

  it('shows an error when the document cannot be loaded', async () => {
    api.fetchOpenApiDocument.mockRejectedValue(new Error('boom'))
    renderContent()
    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the API documentation.')
  })
})

describe('ApiDocsContent without a generated document', () => {
  it('never asks for the document and tells how to generate it', async () => {
    api.fetchApiDocsStatus.mockResolvedValue({ status: 'missing', generated_at: null })
    renderContent()

    expect(await screen.findByRole('status')).toHaveTextContent('The documentation has not been generated yet.')
    expect(api.fetchOpenApiDocument).not.toHaveBeenCalled()
    expect(api.refreshApiDocs).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Download OpenAPI' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Refresh' })).toBeEnabled()
  })
})

describe('ApiDocsContent refresh', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('regenerates on demand, keeps the current document meanwhile and loads the new one when ready', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const regenerated = { ...document, info: { version: '2.0' } }
    api.refreshApiDocs.mockResolvedValue({ ...readyStatus, status: 'generating' })
    renderContent()
    expect(await findSidebar()).toBeInTheDocument()

    api.fetchApiDocsStatus.mockResolvedValue({ status: 'generating', generated_at: readyStatus.generated_at })
    fireEvent.click(screen.getByRole('button', { name: 'Refresh' }))

    expect(await screen.findByRole('button', { name: 'Refreshing…' })).toBeDisabled()
    expect(screen.getByText('Version 1.0')).toBeInTheDocument()

    api.fetchApiDocsStatus.mockResolvedValue({ status: 'ready', generated_at: '2026-11-14T00:00:00Z' })
    api.fetchOpenApiDocument.mockResolvedValue(regenerated)
    await vi.advanceTimersByTimeAsync(GENERATING_POLL_MS)

    expect(await screen.findByText('Version 2.0')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Refresh' })).toBeEnabled()
    expect(api.fetchOpenApiDocument).toHaveBeenCalledTimes(2)
  })

  it('shows the updating status when the first generation is running', async () => {
    api.fetchApiDocsStatus.mockResolvedValue({ status: 'generating', generated_at: null })
    renderContent()

    const status = await screen.findByRole('status')
    expect(status).toHaveTextContent('We are updating the API documentation.')
    expect(status).toHaveAttribute('aria-live', 'polite')
  })

  it('reports a refresh that cannot start', async () => {
    api.refreshApiDocs.mockRejectedValue(new Error('boom'))
    renderContent()

    fireEvent.click(await screen.findByRole('button', { name: 'Refresh' }))
    await vi.waitFor(() => expect(toast.error).toHaveBeenCalledWith('Unable to start the documentation refresh.'))
  })

  it('hides Refresh without the update permission', async () => {
    abilities.granted = ['api-clients.view']
    renderContent()

    expect(await findSidebar()).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Refresh' })).not.toBeInTheDocument()
  })
})
