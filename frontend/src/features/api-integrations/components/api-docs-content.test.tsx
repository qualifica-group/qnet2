import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen } from '@testing-library/react'
import i18n from '@/i18n'
import {
  document,
  findSidebar,
  renderContent,
  resetDocsMocks,
} from '@/features/api-integrations/components/api-docs-fixtures'
import type { NavigationItem } from '@/features/navigation/types'

const api = vi.hoisted(() => ({ fetchOpenApiDocument: vi.fn(), downloadApiDoc: vi.fn() }))
const navigation = vi.hoisted(() => ({ items: [] as NavigationItem[] }))
vi.mock('@/features/api-integrations/api', () => api)
vi.mock('@/features/navigation/use-navigation', () => ({ useNavigation: () => ({ data: navigation.items }) }))
const toast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }))
vi.mock('sonner', () => ({ toast }))
const writeText = vi.fn()

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  resetDocsMocks({ api, navigation, toast, writeText })
})

afterEach(() => {
  window.history.replaceState(null, '', '/')
})

describe('ApiDocsContent header', () => {
  it('shows title, base URL, version and totals', async () => {
    renderContent()

    expect(await screen.findByRole('heading', { name: 'API reference' })).toBeInTheDocument()
    expect(screen.getByText('https://qnet.test/api')).toBeInTheDocument()
    expect(screen.getByText('Version 1.0')).toBeInTheDocument()
    expect(screen.getByText('5 endpoints · 3 modules')).toBeInTheDocument()
    expect(screen.getByText(/may change with updates/)).toBeInTheDocument()
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
describe('ApiDocsContent while the server generates the document (202)', () => {
  const generating = (retryAfterSeconds: number) => ({ status: 'generating', retryAfterSeconds })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows the preparing status with the header, then the reference once the document is ready', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    api.fetchOpenApiDocument
      .mockReset()
      .mockResolvedValueOnce(generating(5))
      .mockResolvedValue({ status: 'ready', document })
    renderContent()

    const status = await screen.findByRole('status')
    expect(status).toHaveTextContent('We are preparing the API documentation.')
    expect(status).toHaveAttribute('aria-live', 'polite')
    expect(screen.getByRole('heading', { name: 'API reference' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Download OpenAPI' })).toBeInTheDocument()
    expect(screen.queryByRole('navigation', { name: 'API modules' })).not.toBeInTheDocument()

    await vi.advanceTimersByTimeAsync(5000)
    expect(await findSidebar()).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(api.fetchOpenApiDocument).toHaveBeenCalledTimes(2)
  })

  it('gives up after the maximum wait and offers a retry', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    api.fetchOpenApiDocument.mockReset().mockResolvedValue(generating(150))
    renderContent()
    await screen.findByRole('status')

    await vi.advanceTimersByTimeAsync(150_000)
    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the API documentation.')

    api.fetchOpenApiDocument.mockResolvedValue({ status: 'ready', document })
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(await findSidebar()).toBeInTheDocument()
  })

  it('tells that the documentation is being prepared when a download answers 202', async () => {
    api.downloadApiDoc.mockResolvedValue('generating')
    renderContent()

    fireEvent.click(await screen.findByRole('button', { name: 'Download Postman collection' }))
    await vi.waitFor(() =>
      expect(toast.info).toHaveBeenCalledWith('Documentation is being prepared, try again shortly.'),
    )
    expect(toast.error).not.toHaveBeenCalled()
  })
})
