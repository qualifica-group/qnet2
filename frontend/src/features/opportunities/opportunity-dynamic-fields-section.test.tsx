import { beforeAll, describe, expect, it } from 'vitest'
import type { ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { OpportunityDynamicFieldsSection } from '@/features/opportunities/opportunity-dynamic-fields-section'
import type { ApplicableAttributeSummary } from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/**
 * "Informazioni aggiuntive" on the opportunity form (user directive
 * 2026-08-05, "come sta in gestione richieste"): the section renders the
 * applicable set through the shared `AttributeLayoutRenderer`, keeps its slot
 * when there is nothing to show, and says "loading" rather than "none" while
 * the create form is still resolving the chosen categories.
 */

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function attribute(overrides: Partial<ApplicableAttributeSummary> = {}): ApplicableAttributeSummary {
  return {
    id: 1,
    code: 'contract_length',
    name: 'Contract length',
    type: 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: false,
    sort_order: 0,
    options: [],
    ...overrides,
  }
}

function Harness({
  attributes = [],
  isLoading = false,
}: {
  attributes?: ApplicableAttributeSummary[]
  isLoading?: boolean
}) {
  const form = useForm<OpportunityFormValues>({
    defaultValues: { attribute_values: { contract_length: '24 months' } },
  })

  return (
    <Form {...form}>
      <OpportunityDynamicFieldsSection
        control={form.control}
        attributes={attributes}
        layout={null}
        isLoading={isLoading}
      />
    </Form>
  )
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ResourcePermissionsProvider permissions={FULL_PERMISSIONS}>{children}</ResourcePermissionsProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('OpportunityDynamicFieldsSection', () => {
  it('renders the applicable attributes, hydrated from the form values', () => {
    render(<Harness attributes={[attribute()]} />, { wrapper: wrapper() })

    // The card title and the block-level `MetaField` label carry the same
    // string, exactly as the Gestione Richieste panel renders them.
    expect(screen.getByRole('heading', { name: 'Additional information' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: /contract length/i })).toHaveValue('24 months')
  })

  it('keeps its slot and says so when the categories carry no attribute', () => {
    render(<Harness />, { wrapper: wrapper() })

    expect(screen.getByText('No additional fields for this opportunity.')).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('reports "loading", not "none", while the create form resolves the chosen categories', () => {
    render(<Harness isLoading />, { wrapper: wrapper() })

    expect(screen.queryByText('No additional fields for this opportunity.')).not.toBeInTheDocument()
    expect(screen.getByText('Loading…')).toBeInTheDocument()
  })
})
