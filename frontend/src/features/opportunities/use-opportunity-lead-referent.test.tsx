import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { createDefaults } from '@/features/opportunities/opportunity-form-defaults'
import { useOpportunityLeadSelection } from '@/features/opportunities/use-opportunity-lead-selection'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** Spec 0330 D-6 / AC-014: a picked Lead hands its anagrafica's main referent to an empty or stale Referente. */
const LEAD_ID = 5
const REGISTRY_ID = 30
const PRIMARY_REFERENT_ID = 55

const fetchMetaMock = vi.fn()
vi.mock('@/features/registries/for-select-meta', () => ({
  fetchRegistryForSelectMeta: (_client: unknown, registryId: number) => fetchMetaMock(registryId),
}))
vi.mock('@/features/opportunities/opportunity-defaults-api', () => ({
  fetchOpportunityDefaultsOnce: () =>
    Promise.resolve({
      lead_id: LEAD_ID,
      existing_opportunity_id: null,
      values: { referent_id: null, source_id: 20, registry_id: REGISTRY_ID, operational_site_id: null },
      references: { source: null, registry: { id: REGISTRY_ID, name: 'Acme' }, operational_site: null },
      locked_fields: [],
      product_lines: [],
      manager_slots: [],
      manager_refs: [],
    }),
}))

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function renderSelection(preset: Partial<OpportunityFormValues>) {
  return renderHook(
    () => {
      const form = useForm<OpportunityFormValues>({ defaultValues: { ...createDefaults(undefined), ...preset } })
      const selection = useOpportunityLeadSelection(null, form.setValue, form.getValues)
      return { form, selection }
    },
    { wrapper },
  )
}

describe('useOpportunityLeadSelection referent prefill (spec 0330 D-6)', () => {
  beforeEach(() => {
    fetchMetaMock.mockReset()
    fetchMetaMock.mockResolvedValue({ primary_referent: { id: PRIMARY_REFERENT_ID, name: 'Rita Verdi' } })
  })

  it('takes the main referent when the lead brings a different anagrafica', async () => {
    const { result } = renderSelection({ registry_id: 99, referent_id: 7 })

    await act(() => result.current.selection.selectLead(LEAD_ID))

    expect(result.current.form.getValues('referent_id')).toBe(PRIMARY_REFERENT_ID)
  })

  it('takes the main referent when the Referente is empty, even on the same anagrafica', async () => {
    const { result } = renderSelection({ registry_id: REGISTRY_ID, referent_id: null })

    await act(() => result.current.selection.selectLead(LEAD_ID))

    expect(result.current.form.getValues('referent_id')).toBe(PRIMARY_REFERENT_ID)
  })

  it('leaves a chosen Referente alone on the same anagrafica', async () => {
    const { result } = renderSelection({ registry_id: REGISTRY_ID, referent_id: 7 })

    await act(() => result.current.selection.selectLead(LEAD_ID))

    expect(result.current.form.getValues('referent_id')).toBe(7)
    expect(fetchMetaMock).not.toHaveBeenCalled()
  })

  it('keeps the lead selection and the Referente when the meta lookup fails', async () => {
    fetchMetaMock.mockRejectedValue(new Error('network'))
    const { result } = renderSelection({ registry_id: 99, referent_id: 7 })

    await act(() => result.current.selection.selectLead(LEAD_ID))

    expect(result.current.selection.state.isError).toBe(false)
    expect(result.current.selection.state.leadId).toBe(LEAD_ID)
    expect(result.current.form.getValues('registry_id')).toBe(REGISTRY_ID)
    expect(result.current.form.getValues('referent_id')).toBe(7)
  })
})
