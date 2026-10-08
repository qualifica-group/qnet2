import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityFormScreen } from '@/features/opportunities/opportunity-screens'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  FULL_PERMISSIONS,
  ROW,
  formTestWrapper,
  openRow,
  queryPencil,
  resolveForSelectLabels,
} from '@/features/opportunities/opportunity-form-test-helpers'

/**
 * Spec 0198 AC-004 / D-5: leaving the create form without saving asks first
 * (`GuardedOpportunityForm`, spec 0195 D-9 applied to Opportunita'). The real
 * form is mounted inside the real screen adapter; only the data sources are
 * stubbed.
 */

vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

vi.mock('@/features/product-lines/product-category-root-select', async () => ({
  ProductCategoryRootSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .ProductCategoryRootSelectDouble,
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .AsyncPaginatedSelectDouble,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args) }
})

const onCancelMock = vi.fn()

async function renderCreateScreen() {
  render(<OpportunityFormScreen mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={onCancelMock} />, {
    wrapper: formTestWrapper(),
  })
  await waitFor(() => expect(queryPencil(ROW.title)).toBeInTheDocument())
}

/** The identity band's Cancel (the footer carries a twin that does the same). */
function clickCancel() {
  fireEvent.click(screen.getAllByRole('button', { name: 'Cancel' })[0])
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  onCancelMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) =>
    resolveForSelectLabels(resource, params),
  )
})

describe('OpportunityFormScreen — leave guard (spec 0198, AC-004)', () => {
  it('asks before Cancel leaves the create form, and stays when the user keeps editing', async () => {
    await renderCreateScreen()
    openRow(ROW.title)
    fireEvent.change(screen.getByRole('textbox', { name: 'Title' }), { target: { value: 'Draft deal' } })

    clickCancel()

    expect(await screen.findByRole('alertdialog')).toHaveTextContent('Leave without saving?')
    fireEvent.click(screen.getByRole('button', { name: 'Keep editing' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(onCancelMock).not.toHaveBeenCalled()
    // The draft is untouched.
    expect(screen.getByRole('textbox', { name: 'Title' })).toHaveValue('Draft deal')
  })

  it('leaves through Cancel once the user confirms', async () => {
    await renderCreateScreen()

    clickCancel()
    fireEvent.click(await screen.findByRole('button', { name: 'Leave without saving' }))

    await waitFor(() => expect(onCancelMock).toHaveBeenCalledTimes(1))
  })
})
