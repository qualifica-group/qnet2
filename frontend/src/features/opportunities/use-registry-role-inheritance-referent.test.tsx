import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { createDefaults } from '@/features/opportunities/opportunity-form-defaults'
import { useRegistryRoleInheritance } from '@/features/opportunities/use-registry-role-inheritance'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** Spec 0330 D-5 / AC-013: picking an anagrafica replaces the Referente with its main referent. */
const metaByRegistry = new Map<number, Promise<unknown>>()
vi.mock('@/features/registries/for-select-meta', () => ({
  fetchRegistryForSelectMeta: (_client: unknown, registryId: number) => metaByRegistry.get(registryId),
}))

function metaOf(primaryReferentId: number | null) {
  return {
    is_qualified_supplier: false,
    commercial: null,
    reporter: null,
    supervisor: null,
    managers: [],
    primary_referent: primaryReferentId === null ? null : { id: primaryReferentId, name: 'Referente' },
  }
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function renderInheritance(referentId: number | null) {
  return renderHook(
    () => {
      const form = useForm<OpportunityFormValues>({ defaultValues: { ...createDefaults(undefined), referent_id: referentId } })
      return { form, pick: useRegistryRoleInheritance(form.setValue, form.getValues) }
    },
    { wrapper },
  )
}

describe('useRegistryRoleInheritance referent (spec 0330 D-5)', () => {
  beforeEach(() => metaByRegistry.clear())

  it('replaces the previous referent with the main referent of the new anagrafica', async () => {
    metaByRegistry.set(1, Promise.resolve(metaOf(55)))
    const { result } = renderInheritance(7)

    await act(() => result.current.pick(1))

    expect(result.current.form.getValues('referent_id')).toBe(55)
  })

  it('leaves the Referente empty for an anagrafica without referents', async () => {
    metaByRegistry.set(2, Promise.resolve(metaOf(null)))
    const { result } = renderInheritance(7)

    await act(() => result.current.pick(2))

    expect(result.current.form.getValues('referent_id')).toBeNull()
  })

  it('lets the latest pick win over a slower earlier response', async () => {
    let resolveSlow: (value: unknown) => void = () => undefined
    metaByRegistry.set(3, new Promise((resolve) => (resolveSlow = resolve)))
    metaByRegistry.set(4, Promise.resolve(metaOf(66)))
    const { result } = renderInheritance(null)

    let slow: Promise<void> = Promise.resolve()
    act(() => {
      slow = result.current.pick(3)
    })
    await act(() => result.current.pick(4))
    await act(async () => {
      resolveSlow(metaOf(55))
      await slow
    })

    expect(result.current.form.getValues('referent_id')).toBe(66)
  })
})
