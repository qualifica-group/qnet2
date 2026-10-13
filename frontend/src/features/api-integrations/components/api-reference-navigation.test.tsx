import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import {
  findSidebar,
  openLeads,
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

describe('ApiDocsContent sidebar and introduction', () => {
  it('groups the modules by menu section with an "Other" fallback and counts', async () => {
    renderContent()
    const sidebar = await findSidebar()

    const marketing = within(sidebar).getByRole('group', { name: 'Marketing & Leads' })
    expect(within(marketing).getByRole('button', { name: /^Leads/ })).toHaveTextContent('3')
    expect(within(sidebar).getByRole('group', { name: 'Configuration' })).toBeInTheDocument()
    const other = within(sidebar).getByRole('group', { name: 'Other' })
    expect(within(other).getByRole('button', { name: /Work order payment statuses/ })).toBeInTheDocument()
  })

  it('opens on the introduction: authentication, common errors and rate limit', async () => {
    renderContent()
    const sidebar = await findSidebar()

    expect(within(sidebar).getByRole('button', { name: 'Introduction' })).toHaveAttribute('aria-current', 'true')
    expect(screen.getByRole('heading', { name: 'Authentication' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Common errors' })).toBeInTheDocument()
    expect(screen.getByText(/Not authenticated/)).toBeInTheDocument()
    expect(screen.getByText(/Retry-After/)).toBeInTheDocument()
    expect(screen.getByText(/requests-per-minute limit/)).toBeInTheDocument()
  })

  it('offers the modules also as a compact select (the list is hidden below lg)', async () => {
    renderContent()
    const sidebar = await findSidebar()
    expect(screen.getByRole('combobox', { name: 'Module' })).toBeInTheDocument()
    expect(sidebar).toHaveClass('hidden', 'lg:flex')
  })

  it('shows the client key and user login examples in cURL, switchable to JavaScript', async () => {
    renderContent()
    await findSidebar()

    expect(screen.getByRole('region', { name: 'Example call' })).toHaveTextContent('curl "https://qnet.test/api/leads/1"')
    expect(screen.getByRole('region', { name: 'User login (POST /auth/client-login)' })).toHaveTextContent(
      'Authorization: Bearer <your-api-key>',
    )
    const [clientTab] = screen.getAllByRole('tab', { name: 'JavaScript' })
    fireEvent.mouseDown(clientTab)
    fireEvent.click(clientTab)
    expect(screen.getByRole('region', { name: 'Example call' })).toHaveTextContent('await fetch(')
  })
})

describe('ApiDocsContent module panel', () => {
  it('lists the endpoints sorted by path then method with cleaned summaries', async () => {
    await openLeads()

    expect(screen.getByRole('heading', { name: 'Leads' })).toBeInTheDocument()
    expect(screen.getByText('3 endpoints')).toBeInTheDocument()
    const rows = screen.getAllByRole('button', { expanded: false })
    expect(rows.map((row) => row.textContent)).toEqual([
      'GET/leadsList leads',
      'POST/leadscreate a lead',
      'DELETE/leads/{lead}Delete a lead',
    ])
  })

  it('expands an endpoint with its fields table and compiled cURL', async () => {
    await openLeads()

    const row = screen.getByRole('button', { name: /POST\/leads/ })
    fireEvent.click(row)
    expect(row).toHaveAttribute('aria-expanded', 'true')

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Body' }))
    fireEvent.click(screen.getByRole('tab', { name: 'Body' }))
    const table = await screen.findByRole('table', { name: 'Body' })
    expect(within(table).getByText('registry_id')).toBeInTheDocument()
    expect(within(table).getByText('required')).toBeInTheDocument()
    expect(within(table).getByText('won')).toBeInTheDocument()
    expect(within(table).getByText('city')).toBeInTheDocument()

    const curl = screen.getByRole('region', { name: 'Request' })
    expect(curl).toHaveTextContent('curl -X POST "https://qnet.test/api/leads"')
    expect(curl).toHaveTextContent('Authorization: Bearer <your-api-key>')
    expect(curl).toHaveTextContent('"registry_id": 0')
    expect(screen.getByRole('region', { name: 'Example response 201' })).toHaveTextContent('"id": 0')
  })

  it('shows the parameters table of a GET endpoint', async () => {
    await openLeads()
    fireEvent.click(screen.getByRole('button', { name: /GET\/leadsList leads/ }))

    const params = await screen.findByRole('table', { name: 'Parameters' })
    expect(within(params).getByText('per_page')).toBeInTheDocument()
    expect(within(params).getByText('Page size')).toBeInTheDocument()
  })

  it('switches the body to the raw JSON schema', async () => {
    await openLeads()
    fireEvent.click(screen.getByRole('button', { name: /POST\/leads/ }))
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Body' }))
    fireEvent.click(screen.getByRole('tab', { name: 'Body' }))

    fireEvent.click(await screen.findByRole('button', { name: 'JSON schema' }))
    expect(screen.getByRole('region', { name: 'Body' })).toHaveTextContent('"registry_id"')
    expect(screen.queryByRole('table', { name: 'Body' })).not.toBeInTheDocument()
  })

  it('copies the deep link of the endpoint', async () => {
    await openLeads()
    fireEvent.click(screen.getByRole('button', { name: /POST\/leads/ }))
    expect(window.location.hash).toBe('#lead.store')

    fireEvent.click(screen.getByRole('button', { name: 'Copy link' }))
    await vi.waitFor(() =>
      expect(writeText).toHaveBeenCalledWith(expect.stringMatching(/\/dev\/api-docs#lead\.store$/)),
    )
  })
})

describe('ApiDocsContent deep link', () => {
  it('selects the module and opens the endpoint named by the hash', async () => {
    window.history.replaceState(null, '', '/dev/api-docs#lead.store')
    renderContent()

    const row = await screen.findByRole('button', { name: /POST\/leads/ })
    expect(row).toHaveAttribute('aria-expanded', 'true')
    expect(within(await findSidebar()).getByRole('button', { name: /^Leads/ })).toHaveAttribute(
      'aria-current',
      'true',
    )
  })
})

describe('ApiDocsContent filters', () => {
  it('searches across modules and keeps only the modules with results', async () => {
    renderContent()
    const sidebar = await findSidebar()
    fireEvent.change(screen.getByRole('searchbox', { name: 'Search endpoints' }), { target: { value: 'sources' } })

    expect(within(sidebar).getByRole('button', { name: /All results/ })).toHaveAttribute('aria-current', 'true')
    expect(within(sidebar).queryByRole('button', { name: /^Leads/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /GET\/sourcesList sources/ })).toBeInTheDocument()

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzz' } })
    expect(screen.getByText('No endpoint matches the filters.')).toBeInTheDocument()
  })

  it('filters by method with live counts', async () => {
    renderContent()
    const group = await screen.findByRole('group', { name: 'Filter by method' })
    expect(within(group).getByRole('button', { name: /POST/ })).toHaveTextContent('1')
    expect(within(group).getByRole('button', { name: /GET/ })).toHaveTextContent('3')

    fireEvent.click(within(group).getByRole('button', { name: /DELETE/ }))
    expect(within(group).getByRole('button', { name: /DELETE/ })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: /DELETE\/leads\/\{lead\}/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /GET\/sources/ })).not.toBeInTheDocument()
  })

  it('focuses the search box with "/" outside of fields', async () => {
    renderContent()
    const search = await screen.findByRole('searchbox')
    fireEvent.keyDown(globalThis.document.body, { key: '/' })
    expect(search).toHaveFocus()
  })
})
