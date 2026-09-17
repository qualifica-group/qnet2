import { describe, expect, it, vi, beforeEach } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { OpportunityContactRecap } from '@/features/opportunities/opportunity-contact-recap'
import type { PaginatedResponse } from '@/features/for-select/types'
import type { ReferentContact } from '@/features/referents/use-referent-contacts'

/** AC-094: the recap reads a selected referent's PRIMARY contacts from `referents/for-select` `meta.contacts`. */

const fetchReferentsForSelectMock = vi.fn()
vi.mock('@/features/referents/for-select-api', async () => {
  const actual = await vi.importActual<typeof import('@/features/referents/for-select-api')>(
    '@/features/referents/for-select-api',
  )
  return {
    ...actual,
    fetchReferentsForSelect: (...args: unknown[]) => fetchReferentsForSelectMock(...args),
  }
})

interface ReferentForSelectItemWithMeta {
  id: number
  label: string
  meta: { contacts: ReferentContact[] }
}

function page(id: number, contacts: ReferentContact[]): PaginatedResponse<ReferentForSelectItemWithMeta> {
  return {
    items: [{ id, label: 'Ada Lovelace', meta: { contacts } }],
    pagination: { offset: 0, limit: 25, total: 0, total_pages: 0 },
    export_link: null,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  fetchReferentsForSelectMock.mockReset()
})

describe('OpportunityContactRecap (AC-094)', () => {
  it('renders nothing when no referent is selected', () => {
    render(<OpportunityContactRecap referentId={null} />, { wrapper: wrapper() })

    expect(fetchReferentsForSelectMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('list')).not.toBeInTheDocument()
  })

  it('shows the selected referent primary contacts', async () => {
    fetchReferentsForSelectMock.mockResolvedValue(
      page(7, [
        { type: 'email', label: 'Work', value: 'ada@example.test', is_primary: true },
        { type: 'phone', label: null, value: '+39 333 1112223', is_primary: true },
      ]),
    )

    render(<OpportunityContactRecap referentId={7} />, { wrapper: wrapper() })

    await waitFor(() => expect(screen.getByText('ada@example.test')).toBeInTheDocument())
    expect(screen.getByText('+39 333 1112223')).toBeInTheDocument()
    expect(fetchReferentsForSelectMock).toHaveBeenCalledWith({ ids: [7] })
  })

  it('renders nothing when the referent has no primary contacts', async () => {
    fetchReferentsForSelectMock.mockResolvedValue(page(8, []))

    render(<OpportunityContactRecap referentId={8} />, { wrapper: wrapper() })

    await waitFor(() => expect(fetchReferentsForSelectMock).toHaveBeenCalledWith({ ids: [8] }))
    expect(screen.queryByRole('list')).not.toBeInTheDocument()
  })
})
