import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import {
  EDITABLE,
  applyRow,
  categoryWrapper,
  category as baseCategory,
  permissionsFor,
  saveRow,
} from '@/features/product-categories/product-category-test-fixtures'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0021: product-categories is one of the universal custom-fields
 * rollout modules — mounting `<CustomFieldsSection>` is the ONLY
 * product-categories-specific integration, ADDITIONAL to (and independent
 * from) the category's own attribute-assignment editor. This suite exercises
 * the wiring (schema, defaults, payload, 422) without touching the section's
 * own per-type rendering (covered by `CustomFieldsSection.test.tsx`).
 */

const createProductCategoryMock = vi.fn()
const updateProductCategoryMock = vi.fn()

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  createProductCategory: (...args: unknown[]) => createProductCategoryMock(...args),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => Promise.resolve([]),
  fetchEffectiveAttributes: () => Promise.resolve([]),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// This suite isn't about the quick-create "+" (spec 0028): every relation
// field's `QuickCreateButton` (the business function picker, the relation
// custom field control) reads `useAbilities()` via `Can`, which needs an
// `AuthProvider` this suite doesn't render.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithNotes(): ResourcePermissions {
  return permissionsFor({ 'custom.notes': EDITABLE })
}

function wrapper() {
  return categoryWrapper().wrapper
}

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: wrapper() })
}

function category(overrides: Partial<ProductCategoryDetailWithPermissions> = {}): ProductCategoryDetailWithPermissions {
  return baseCategory({ permissions: permissionsWithNotes(), ...overrides })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductCategoryMock.mockReset()
  updateProductCategoryMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissionsWithNotes() })
})

describe('custom fields rows (spec 0021, spec 0229)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Notes' }))

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createProductCategoryMock.mockResolvedValue(category())
    const onSuccess = vi.fn()

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Name' }))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Laptops' } })
    applyRow()
    fireEvent.click(screen.getByRole('button', { name: 'Edit Notes' }))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Notes' }), {
      target: { value: 'Key category' },
    })
    applyRow()
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createProductCategoryMock).toHaveBeenCalledTimes(1))
    const payload = createProductCategoryMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ notes: 'Key category' })
  })

  it('seeds the custom field value from the loaded category detail in edit mode', async () => {
    renderDetail(category({ custom_fields: { notes: 'Existing note' } }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Notes' }))

    expect(await screen.findByRole('textbox', { name: 'Notes' })).toHaveValue('Existing note')
  })

  it('sends only the changed custom field on a partial update', async () => {
    const original = category({ custom_fields: { notes: 'Existing note' } })
    updateProductCategoryMock.mockResolvedValue(original)

    renderDetail(original)

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Notes' }))
    const notes = await screen.findByRole('textbox', { name: 'Notes' })
    fireEvent.change(notes, { target: { value: 'Updated note' } })
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ custom_fields: { notes: 'Updated note' } })
  })

  it('maps a 422 on custom_fields.<key> inline on the matching control', async () => {
    updateProductCategoryMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: {
            success: false,
            message: 'Validation failed',
            errors: { 'custom_fields.notes': ['Notes must be shorter.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    renderDetail(category({ custom_fields: { notes: 'Existing note' } }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Notes' }))
    await screen.findByRole('textbox', { name: 'Notes' })
    saveRow()

    await waitFor(() => expect(screen.getByText('Notes must be shorter.')).toBeInTheDocument())
    expect(updateProductCategoryMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
