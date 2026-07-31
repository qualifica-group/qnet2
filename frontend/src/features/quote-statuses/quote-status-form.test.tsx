import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuoteStatusForm } from '@/features/quote-statuses/quote-status-form'
import type { QuoteStatusDetailWithPermissions } from '@/features/quote-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createQuoteStatusMock = vi.fn()
const updateQuoteStatusMock = vi.fn()

vi.mock('@/features/quote-statuses/api', () => ({
  createQuoteStatus: (...args: unknown[]) => createQuoteStatusMock(...args),
  updateQuoteStatus: (...args: unknown[]) => updateQuoteStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Every field resolves as visible+editable (the `MetaField` fallback, since
 * `fields` is empty) — not about authorization metadata.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/quote-statuses/use-quote-status-form-meta', () => ({
  useQuoteStatusFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function quoteStatus(
  overrides: Partial<QuoteStatusDetailWithPermissions> = {},
): QuoteStatusDetailWithPermissions {
  return {
    id: 9,
    name: 'Bozza',
    color: 'blue',
    sort_order: 1,
    system_key: null,
    group: 'open',
    created_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createQuoteStatusMock.mockReset()
  updateQuoteStatusMock.mockReset()
})

describe('QuoteStatusForm — create/edit (spec 0065)', () => {
  it('renders the name, color and group fields in create mode, with no order input', () => {
    render(
      <QuoteStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a color/i })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Group' })).toHaveTextContent('Open')
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('submits the create payload on save, without sort_order', async () => {
    createQuoteStatusMock.mockResolvedValue(quoteStatus())
    const onSuccess = vi.fn()

    render(
      <QuoteStatusForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Bozza' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createQuoteStatusMock).toHaveBeenCalledTimes(1))
    expect(createQuoteStatusMock).toHaveBeenCalledWith({
      name: 'Bozza',
      color: null,
      group: 'open',
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(quoteStatus()))
  })

  it('hydrates name, color and group in edit mode', () => {
    render(
      <QuoteStatusForm
        mode={{ type: 'edit', quoteStatus: quoteStatus({ group: 'pending' }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Bozza')
    expect(screen.getByRole('button', { name: /Blue/ })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Group' })).toHaveTextContent('Pending')
  })

  it('submits only the changed name on a partial update', async () => {
    updateQuoteStatusMock.mockResolvedValue(quoteStatus({ name: 'Rifiutata' }))

    render(
      <QuoteStatusForm
        mode={{ type: 'edit', quoteStatus: quoteStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Rifiutata' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateQuoteStatusMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateQuoteStatusMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Rifiutata' })
  })

  it('submits the newly picked group on change', async () => {
    updateQuoteStatusMock.mockResolvedValue(quoteStatus({ group: 'closed_lost' }))

    render(
      <QuoteStatusForm
        mode={{ type: 'edit', quoteStatus: quoteStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Group' }))
    fireEvent.click(screen.getByRole('option', { name: 'Closed (negative)' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateQuoteStatusMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateQuoteStatusMock.mock.calls[0]
    expect(payload).toEqual({ group: 'closed_lost' })
  })
})

describe('QuoteStatusForm — system row (D-2)', () => {
  it('disables the group field and shows a hint, while name/color stay editable', () => {
    render(
      <QuoteStatusForm
        mode={{
          type: 'edit',
          quoteStatus: quoteStatus({ name: 'Bozza', system_key: 'new', group: 'open' }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).not.toBeDisabled()
    expect(screen.getByRole('button', { name: /Blue/ })).not.toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Group' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'More information' })).toBeInTheDocument()
  })
})
