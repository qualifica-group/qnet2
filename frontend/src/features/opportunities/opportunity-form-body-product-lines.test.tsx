import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  FULL_PERMISSIONS,
  ROW,
  SELECT_IDS,
  applyRow,
  clickSave,
  formTestWrapper,
  openRow,
  queryPencil,
} from '@/features/opportunities/opportunity-form-test-helpers'

/**
 * Spec 0132 (supersedes AC-106): the row's two-step pick — root category then
 * one of its descendants, no business-function select any more. Split out of
 * `opportunity-form-body.test.tsx` when it crossed the 500-line hard limit
 * (engineering.md §6). Spec 0198: the classification rows live in ONE closed
 * row of the create form, opened through its pencil.
 */

const createOpportunityMock = vi.fn()

vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

const TEST_ROOT_CATEGORY = 30

vi.mock('@/features/product-lines/product-category-root-select', async () => ({
  ProductCategoryRootSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .ProductCategoryRootSelectDouble,
}))

/** Every OTHER relation field stays inert (no quick-create `<Can>`, which needs an `AuthProvider`). */
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .AsyncPaginatedSelectDouble,
}))

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return { ...actual, createOpportunity: (...args: unknown[]) => createOpportunityMock(...args) }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const INCOMPLETE_ROW_MESSAGE = 'Each row requires both a parent category and a product category.'

async function renderCreateForm() {
  render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: formTestWrapper(),
  })
  await waitFor(() => expect(queryPencil(ROW.title)).toBeInTheDocument())
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  for (const key of Object.keys(SELECT_IDS)) {
    delete SELECT_IDS[key]
  }
  SELECT_IDS['Parent category 1'] = [TEST_ROOT_CATEGORY]
  createOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
})

describe('OpportunityFormBody — product lines (spec 0132)', () => {
  it("scopes the row category by the row's own root category", async () => {
    await renderCreateForm()

    openRow(ROW.productLines)
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')

    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${TEST_ROOT_CATEGORY}` }))

    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    expect(screen.getByTestId('scope-Product category 1')).toHaveTextContent(
      JSON.stringify({ kind: 'root', rootCategoryId: TEST_ROOT_CATEGORY }),
    )
  })

  // REQUIREMENT CHANGED (spec 0198): the error is shown under the CLOSED row
  // (Done validates that field, and Save refuses the whole draft) instead of
  // under an always-open row editor.
  it('blocks the submit and shows an error when a row is left incomplete', async () => {
    await renderCreateForm()

    openRow(ROW.productLines)
    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${TEST_ROOT_CATEGORY}` }))
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    // The row's category is left unset on purpose.
    applyRow()

    // The row closed with the draft kept, and its message shows right there.
    expect(screen.queryByTestId('select-Parent category 1')).not.toBeInTheDocument()
    expect(await screen.findByText(INCOMPLETE_ROW_MESSAGE)).toBeInTheDocument()

    clickSave()

    await waitFor(() => expect(screen.getByText(INCOMPLETE_ROW_MESSAGE)).toBeInTheDocument())
    expect(createOpportunityMock).not.toHaveBeenCalled()
  })
})
