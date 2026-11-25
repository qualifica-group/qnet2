import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { updateAttribute } from '@/features/attributes/api'
import { AttributeDetailView } from '@/features/attributes/attribute-detail'
import type { AttributeDetailWithPermissions } from '@/features/attributes/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Spec 0429 AC-011: the "Campo attuale" row of an attribute offers the fields from
 * `GET /attributes/for-select` (the edited one excluded), sends `current_attribute_id`
 * only when it changes, clears to null and is read-only without the permission.
 */

vi.mock('@/features/attributes/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/attributes/api')>()),
  updateAttribute: vi.fn(),
}))
vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({ data: [], isLoading: false, isError: false }),
  useCustomFieldRelationTargets: () => ({ data: [], isLoading: false, isError: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

const LABEL = 'Current field'
const CURRENT = { id: 9, code: 'iso_assignment_date', name: 'Data di assegnazione' }
const CURRENT_LABEL = 'Data di assegnazione (iso_assignment_date)'

function permissions(update = true): ResourcePermissions {
  return {
    resource: { view: true, create: true, update, delete: true, export: true, import: true },
    fields: {},
    actions: {},
  }
}

function legacyField(overrides: Partial<AttributeDetailWithPermissions> = {}): AttributeDetailWithPermissions {
  return {
    id: 3,
    code: 'data_inizio_lavorazione',
    name: 'Data di assegnazione',
    type: 'date',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    current_attribute_id: null,
    current_attribute: null,
    options: [],
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissions(),
    ...overrides,
  }
}

function queryState(items: ForSelectItem[] = []) {
  return {
    data: { pages: [{ items }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  }
}

function renderDetail(value: AttributeDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<AttributeDetailView attribute={value} />, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencil = () => screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: LABEL }) })
const save = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateAttribute).mockReset()
  vi.mocked(updateAttribute).mockImplementation(async () => legacyField())
  fetchResourceMetaMock.mockReset().mockResolvedValue({ fields: [], permissions: permissions() })
  useForSelectMock.mockReset().mockReturnValue(queryState())
})

describe('Current field row (spec 0429 AC-011)', () => {
  it('shows the saved current field', () => {
    renderDetail(legacyField({ current_attribute_id: CURRENT.id, current_attribute: CURRENT }))

    expect(screen.getByText(CURRENT_LABEL)).toBeInTheDocument()
    expect(pencil()).toBeInTheDocument()
  })

  it('offers the fields without the edited one and saves the pick alone', async () => {
    useForSelectMock.mockReturnValue(queryState([{ id: CURRENT.id, label: CURRENT_LABEL }]))

    renderDetail(legacyField())
    fireEvent.click(pencil() as HTMLElement)
    fireEvent.click(await screen.findByRole('combobox', { name: LABEL }))
    fireEvent.click(screen.getByRole('option', { name: CURRENT_LABEL }))
    save()

    await waitFor(() => expect(updateAttribute).toHaveBeenCalledTimes(1))
    expect(vi.mocked(updateAttribute).mock.calls[0]).toEqual([3, { current_attribute_id: CURRENT.id }])
    expect(useForSelectMock).toHaveBeenCalledWith(expect.objectContaining({ resource: 'attributes', params: { exclude_id: 3 } }))
  })

  it('clearing the link sends null', async () => {
    renderDetail(legacyField({ current_attribute_id: CURRENT.id, current_attribute: CURRENT }))
    fireEvent.click(pencil() as HTMLElement)
    fireEvent.click(await screen.findByRole('button', { name: `Clear ${CURRENT_LABEL}` }))
    save()

    await waitFor(() => expect(updateAttribute).toHaveBeenCalledTimes(1))
    expect(vi.mocked(updateAttribute).mock.calls[0]).toEqual([3, { current_attribute_id: null }])
  })

  it('offers no pencil without the update permission', () => {
    renderDetail(legacyField({ permissions: permissions(false) }))

    expect(screen.getByText(LABEL)).toBeInTheDocument()
    expect(pencil()).not.toBeInTheDocument()
  })
})
