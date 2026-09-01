import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { lazy, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ImportConfigRelationSelect } from '@/features/imports/wizard/import-config-relation-select'
import '@/features/imports/wizard/i18n'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'

/**
 * Spec 0028 applied to the import wizard's global-configuration step: the
 * campaign/source pickers carry the quick-create "+" like every other
 * relation select, so a missing campaign can be created without leaving the
 * run. Uses the real `AsyncPaginatedSelect`; only the options query, the
 * registry lookup and the abilities are stubbed.
 */

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

const resolveQuickCreateMock = vi.fn<(resource: string) => QuickCreateEntry | null>()
vi.mock('@/features/quick-create/quick-create-registry', () => ({
  resolveQuickCreate: (resource: string) => resolveQuickCreateMock(resource),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const CREATED = { id: 42, name: 'Campagna estiva' }

function fakeEntry(): QuickCreateEntry {
  return {
    titleKey: 'campaigns.form.createTitle',
    descriptionKey: 'campaigns.form.createSubtitle',
    permission: 'campaigns.create',
    form: lazy(async () => ({
      default: ({ onSuccess }: QuickCreateFormProps) => (
        <button type="button" onClick={() => onSuccess(CREATED)}>
          fake-submit
        </button>
      ),
    })),
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

function wrapper() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue(queryState())
  resolveQuickCreateMock.mockReset()
  resolveQuickCreateMock.mockReturnValue(fakeEntry())
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('ImportConfigRelationSelect', () => {
  it('renders the quick-create "+" next to the picker', async () => {
    render(
      <ImportConfigRelationSelect resource="campaigns" value={null} onChange={vi.fn()} triggerLabel="Campaign" />,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('combobox', { name: 'Campaign' })).toBeInTheDocument()
    expect(
      await screen.findByRole('button', { name: i18n.t('campaigns.form.createTitle') }),
    ).toBeInTheDocument()
  })

  it('selects the freshly created record and keeps it visible before the options page catches up', async () => {
    const onChange = vi.fn()
    const { rerender } = render(
      <ImportConfigRelationSelect resource="campaigns" value={null} onChange={onChange} triggerLabel="Campaign" />,
      { wrapper: wrapper() },
    )

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('campaigns.form.createTitle') }))
    fireEvent.click(await screen.findByRole('button', { name: 'fake-submit' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith(CREATED.id))

    rerender(
      <ImportConfigRelationSelect
        resource="campaigns"
        value={CREATED.id}
        onChange={onChange}
        triggerLabel="Campaign"
      />,
    )

    expect(screen.getByRole('combobox', { name: 'Campaign' })).toHaveTextContent(CREATED.name)
  })

  it('renders no "+" when the actor lacks the module create permission', async () => {
    canMock.mockReturnValue(false)

    render(
      <ImportConfigRelationSelect resource="campaigns" value={null} onChange={vi.fn()} triggerLabel="Campaign" />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('combobox', { name: 'Campaign' })
    expect(screen.queryByRole('button', { name: i18n.t('campaigns.form.createTitle') })).toBeNull()
  })
})
