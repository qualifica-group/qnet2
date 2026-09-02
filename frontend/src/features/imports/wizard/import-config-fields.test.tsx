import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { useForm } from 'react-hook-form'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ImportConfigFields } from '@/features/imports/wizard/import-config-fields'
import { withConfigDefaults } from '@/features/imports/wizard/import-config-schema'
import type { ImportMappingFormValues } from '@/features/imports/wizard/import-mapping-schema'
import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'
import '@/features/imports/wizard/i18n'

/**
 * Spec 0094 AC-050: `product_ids` is declared `multiple: true, depends_on:
 * 'campaign_id'` — the wizard must render it as a multi-select (not the
 * legacy single relation select), scoped to the chosen campaign's effective
 * product categories, and disabled until a campaign is chosen.
 *
 * `ImportConfigRelationSelect` (the campaign field's own control) is stubbed
 * to a trivial combobox — its own behaviour (quick-create, options query) is
 * already covered by `import-config-relation-select.test.tsx`; this file
 * only owns the `multiple`/`depends_on` branching.
 */

const useForSelectMock = vi.fn()
const useForSelectLabelsMock = vi.fn()

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: (args: unknown) => useForSelectLabelsMock(args),
  }
})

vi.mock('@/features/imports/wizard/import-config-relation-select', () => ({
  ImportConfigRelationSelect: ({
    resource,
    value,
    onChange,
    triggerLabel,
  }: {
    resource: string
    value: number | null
    onChange: (next: number | null) => void
    triggerLabel: string
  }) => (
    <button type="button" aria-label={triggerLabel} onClick={() => onChange(resource === 'campaigns' ? 9 : 1)}>
      {value ?? 'none'}
    </button>
  ),
}))

function queryState() {
  return {
    data: { pages: [{ items: [] }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  }
}

const PRODUCT_FIELD: ImportGlobalFieldDescriptor = {
  id: 'product_ids',
  label: 'Products',
  required: false,
  for_select_resource: 'products',
  default: null,
  multiple: true,
  depends_on: 'campaign_id',
}

const CAMPAIGN_FIELD: ImportGlobalFieldDescriptor = {
  id: 'campaign_id',
  label: 'Campaign',
  required: true,
  for_select_resource: 'campaigns',
  default: null,
}

function renderFields(globalFields: ImportGlobalFieldDescriptor[], initialCampaignId: number | null = null) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  function Harness() {
    const form = useForm<ImportMappingFormValues>({
      defaultValues: {
        mapping: {},
        dedup_strategy: '',
        global_config: withConfigDefaults(
          globalFields,
          initialCampaignId != null ? { campaign_id: initialCampaignId } : {},
        ),
      },
    })
    return (
      <Form {...form}>
        <ImportConfigFields globalFields={globalFields} control={form.control} />
      </Form>
    )
  }

  return render(
    <QueryClientProvider client={queryClient}>
      <Harness />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue(queryState())
  useForSelectLabelsMock.mockReset()
  useForSelectLabelsMock.mockReturnValue(new Map())
})

describe('ImportConfigFields — multiple/depends_on (spec 0094 AC-050)', () => {
  it('renders the multiple field as a multi-select, disabled with a hint until the dependency is chosen', () => {
    renderFields([CAMPAIGN_FIELD, PRODUCT_FIELD])

    const trigger = screen.getByRole('button', { name: 'Products' })
    expect(trigger).toBeDisabled()
    expect(screen.getByText('Choose a Campaign first to select its products.')).toBeInTheDocument()
  })

  it("enables the multi-select and scopes it by the campaign's effective categories once chosen", () => {
    useForSelectLabelsMock.mockImplementation((args: { resource: string; ids: number[] }) => {
      if (args.resource === 'campaigns' && args.ids.includes(9)) {
        return new Map([[9, { id: 9, label: 'Spring campaign', meta: { product_category_ids: [3, 4] } }]])
      }
      return new Map()
    })

    renderFields([CAMPAIGN_FIELD, PRODUCT_FIELD], 9)

    const trigger = screen.getByRole('button', { name: 'Products' })
    expect(trigger).not.toBeDisabled()

    expect(useForSelectLabelsMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'campaigns', ids: [9], enabled: true }),
    )
    expect(useForSelectMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'products', params: { category_ids: [3, 4] } }),
    )
  })

  it('renders a plain relation select for a non-multiple field (legacy behaviour unchanged)', () => {
    renderFields([CAMPAIGN_FIELD])

    expect(screen.getByRole('button', { name: 'Campaign' })).toBeInTheDocument()
  })
})
