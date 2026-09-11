import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { lazy } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'

/** Spec 0028 lane C — multi-select sibling of `relation-select-field.test.tsx`, focused on AC-010 (adds, never replaces). */

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

const resolveQuickCreateMock = vi.fn<(resource: string) => QuickCreateEntry | null>()
vi.mock('@/features/quick-create/quick-create-registry', () => ({
  resolveQuickCreate: (resource: string) => resolveQuickCreateMock(resource),
}))

function fakeEntry(ref: { id: number; name: string }): QuickCreateEntry {
  return {
    titleKey: 'sectors.form.createTitle',
    descriptionKey: 'sectors.form.createSubtitle',
    permission: 'sectors.create',
    form: lazy(async () => ({
      default: ({ onSuccess }: QuickCreateFormProps) => (
        <button type="button" onClick={() => onSuccess(ref)}>
          fake-submit
        </button>
      ),
    })),
  }
}

interface FormValues {
  sector_ids: number[]
}

interface HarnessProps {
  defaultValues?: FormValues
  excludeIds?: number[]
}

function Harness({ defaultValues = { sector_ids: [1] }, excludeIds }: HarnessProps) {
  const form = useForm<FormValues>({ defaultValues })
  return (
    <Form {...form}>
      <RelationMultiSelectField
        control={form.control}
        name="sector_ids"
        metaKey="sector_ids"
        label="Sectors"
        resource="sectors"
        searchPlaceholder="Search sectors…"
        selected={[{ id: 1, name: 'Existing sector' }]}
        placeholder="Select sectors…"
        emptyLabel="No sectors found."
        errorLabel="Unable to load sectors."
        removeLabel="Remove"
        retryLabel="Retry"
        excludeIds={excludeIds}
      />
    </Form>
  )
}

function renderHarness(props: HarnessProps = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue({
    data: { pages: [{ items: [] }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  })
  canMock.mockReset()
  canMock.mockReturnValue(true)
  resolveQuickCreateMock.mockReset()
  resolveQuickCreateMock.mockReturnValue(fakeEntry({ id: 99, name: 'Nuovo settore' }))
})

describe('RelationMultiSelectField quick-create wiring', () => {
  it('adds the newly created record to the existing selection instead of replacing it (AC-010)', async () => {
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('sectors.form.createTitle') }))
    fireEvent.click(await screen.findByRole('button', { name: 'fake-submit' }))

    await waitFor(() => expect(screen.getByText('Nuovo settore')).toBeInTheDocument())
    expect(screen.getByText('Existing sector')).toBeInTheDocument()
  })

  it('renders no "+" without the {domain}.create permission', () => {
    canMock.mockReturnValue(false)
    renderHarness()

    expect(
      screen.queryByRole('button', { name: i18n.t('sectors.form.createTitle') }),
    ).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sectors' })).toBeInTheDocument()
  })
})

/** Spec 0118 AC-035: the wrapper forwards `excludeIds` to `AsyncPaginatedMultiSelect` unchanged. */
describe('RelationMultiSelectField excludeIds', () => {
  beforeEach(() => {
    useForSelectMock.mockReturnValue({
      data: {
        pages: [
          {
            items: [
              { id: 2, label: 'Manufacturing' },
              { id: 3, label: 'Retail' },
            ],
          },
        ],
      },
      isPending: false,
      isError: false,
      fetchNextPage: vi.fn(),
      hasNextPage: false,
      isFetchingNextPage: false,
      refetch: vi.fn(),
    })
  })

  it('drops the excluded option from the popup', () => {
    renderHarness({ excludeIds: [3] })

    fireEvent.click(screen.getByRole('button', { name: 'Sectors' }))

    expect(screen.getByRole('option', { name: 'Manufacturing' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Retail' })).not.toBeInTheDocument()
  })

  it('offers every option when excludeIds is omitted', () => {
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'Sectors' }))

    expect(screen.getByRole('option', { name: 'Manufacturing' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Retail' })).toBeInTheDocument()
  })
})
