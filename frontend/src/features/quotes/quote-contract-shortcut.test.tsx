import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, renderHook, screen } from '@testing-library/react'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { QuoteDetailHeader } from '@/features/quotes/quote-detail-header'
import { WORKFLOW_STATUS_OPEN } from '@/features/quotes/quote-fixtures'
import { useQuoteRowActions } from '@/features/quotes/use-quote-row-actions'
import type { UseModuleOpenerOptions } from '@/features/modules/use-module-opener'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Spec 0315, AC-004: from a closed-won Offerta the Contract born from it opens
 * in a modal — "Open contract" in the detail header and the `contract` row
 * action. `useModuleOpener` is stubbed per domain: what matters here is WHICH
 * record opens and in which mode, not the Sheet itself (covered by its suite).
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const openViewByDomain: Record<string, ReturnType<typeof vi.fn>> = {}
const openerOptionsByDomain: Record<string, UseModuleOpenerOptions | undefined> = {}
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (domain: string, options?: UseModuleOpenerOptions) => {
    openViewByDomain[domain] ??= vi.fn()
    openerOptionsByDomain[domain] = options
    return {
      openCreate: vi.fn(),
      openCreateWith: vi.fn(),
      openView: openViewByDomain[domain],
      openDuplicate: vi.fn(),
      sheet: null,
    }
  },
}))

const CONTRACT_ACTION: TableActionDefinition = {
  key: 'contract',
  label: 'actions.openContract',
  icon: 'file-signature',
  type: 'action',
  confirm: false,
}

function quoteWith(contractId: number | null): QuoteDetailWithPermissions {
  return {
    id: 9,
    code: 'QUO-0009',
    title: 'Sample quote',
    old_id: null,
    quote_workflow_status: WORKFLOW_STATUS_OPEN,
    contract_id: contractId,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
  } as unknown as QuoteDetailWithPermissions
}

let client: QueryClient

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  canMock.mockReturnValue(true)
})

describe('QuoteDetailHeader — Open contract', () => {
  it('opens the contract of the offer in a modal', () => {
    render(<QuoteDetailHeader quote={quoteWith(77)} />, { wrapper })
    fireEvent.click(screen.getByRole('button', { name: 'Open contract' }))

    expect(openViewByDomain.contracts).toHaveBeenCalledWith({ id: 77, actions: [] })
    expect(openerOptionsByDomain.contracts?.forceMode).toBe('modal')
  })

  it('hides the button when the offer has no contract', () => {
    render(<QuoteDetailHeader quote={quoteWith(null)} />, { wrapper })

    expect(screen.queryByRole('button', { name: 'Open contract' })).not.toBeInTheDocument()
  })

  it('hides the button without contracts.view', () => {
    canMock.mockImplementation((permission) => permission !== 'contracts.view')
    render(<QuoteDetailHeader quote={quoteWith(77)} />, { wrapper })

    expect(screen.queryByRole('button', { name: 'Open contract' })).not.toBeInTheDocument()
  })
})

describe('useQuoteRowActions — contract action', () => {
  function handleContract(row: TableRow) {
    const { result } = renderHook(() => useQuoteRowActions({ onMutated: vi.fn() }), { wrapper })
    result.current.handleAction(CONTRACT_ACTION, row)
  }

  it("opens the row's contract in a modal", () => {
    handleContract({ id: 9, actions: ['contract'], contract_id: 77 })

    expect(openViewByDomain.contracts).toHaveBeenCalledWith({ id: 77, actions: [] })
    expect(openerOptionsByDomain.contracts?.forceMode).toBe('modal')
  })

  it('does nothing on a row without contract_id', () => {
    handleContract({ id: 9, actions: ['contract'], contract_id: null })

    expect(openViewByDomain.contracts).not.toHaveBeenCalled()
  })
})
