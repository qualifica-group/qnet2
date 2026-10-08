import { describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { createDefaults } from '@/features/opportunities/opportunity-form-defaults'
import { useOpportunityRegistryPreset } from '@/features/opportunities/use-opportunity-registry-preset'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/**
 * Spec 0199: a create opened on an anagrafica (its detail's "New opportunity")
 * starts on that anagrafica and inherits its roles exactly like a manual pick
 * — once, so a later edit is never overwritten.
 */
const fetchRegistryMetaMock = vi.fn()
vi.mock('@/features/opportunities/opportunity-relation-meta', () => ({
  fetchOpportunityRegistryMeta: (_client: unknown, registryId: number) => fetchRegistryMetaMock(registryId),
}))

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function renderPreset(registryId: number | undefined) {
  return renderHook(
    ({ id }: { id: number | undefined }) => {
      const form = useForm<OpportunityFormValues>({ defaultValues: createDefaults(undefined, id) })
      useOpportunityRegistryPreset(id, form.setValue, form.getValues)
      return form
    },
    { wrapper, initialProps: { id: registryId } },
  )
}

describe('useOpportunityRegistryPreset (spec 0199)', () => {
  it("opens on the anagrafica and hands down its roles once", async () => {
    fetchRegistryMetaMock.mockResolvedValue({
      commercial: { id: 71, name: 'Sara Conti' },
      reporter: { id: 81, name: 'Elio Fabbri' },
      supervisor: { id: 61, name: 'Ivo Bianchi' },
      managers: [],
    })
    const { result, rerender } = renderPreset(30)

    expect(result.current.getValues('registry_id')).toBe(30)
    await waitFor(() => expect(result.current.getValues('commercial_id')).toBe(71))
    expect(result.current.getValues('reporter_id')).toBe(81)
    expect(result.current.getValues('supervisor_id')).toBe(61)

    rerender({ id: 30 })
    expect(fetchRegistryMetaMock).toHaveBeenCalledTimes(1)
  })

  it('does nothing on a plain create', () => {
    fetchRegistryMetaMock.mockClear()
    const { result } = renderPreset(undefined)

    expect(result.current.getValues('registry_id')).toBeNull()
    expect(fetchRegistryMetaMock).not.toHaveBeenCalled()
  })
})
