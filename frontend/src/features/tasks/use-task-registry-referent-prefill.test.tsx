import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useTaskRegistryReferentPrefill } from '@/features/tasks/use-task-registry-referent-prefill'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/** Spec 0330 D-7 / AC-015: the Referente takes the picked anagrafica's main referent; only the last pick counts. */
const metaByRegistry = new Map<number, Promise<unknown>>()
vi.mock('@/features/registries/for-select-meta', () => ({
  fetchRegistryForSelectMeta: (_client: unknown, registryId: number) => metaByRegistry.get(registryId),
}))

function metaOf(primaryReferentId: number | null) {
  return { primary_referent: primaryReferentId === null ? null : { id: primaryReferentId, name: 'Referente' } }
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function renderPrefill() {
  return renderHook(
    () => {
      const form = useForm<TaskFormValues>({ defaultValues: { registry_id: null, referent_id: null } as TaskFormValues })
      return { form, prefill: useTaskRegistryReferentPrefill(form.setValue, form.getValues) }
    },
    { wrapper },
  )
}

describe('useTaskRegistryReferentPrefill (spec 0330 D-7)', () => {
  beforeEach(() => metaByRegistry.clear())

  it('sets the main referent of the picked anagrafica', async () => {
    metaByRegistry.set(1, Promise.resolve(metaOf(55)))
    const { result } = renderPrefill()
    result.current.form.setValue('registry_id', 1)

    await act(() => result.current.prefill(1))

    expect(result.current.form.getValues('referent_id')).toBe(55)
  })

  it('leaves the Referente empty for an anagrafica without referents', async () => {
    metaByRegistry.set(2, Promise.resolve(metaOf(null)))
    const { result } = renderPrefill()
    result.current.form.setValue('registry_id', 2)

    await act(() => result.current.prefill(2))

    expect(result.current.form.getValues('referent_id')).toBeNull()
  })

  it('keeps only the last of two quick picks, whatever order the responses arrive in', async () => {
    let resolveSlow: (value: unknown) => void = () => undefined
    metaByRegistry.set(3, new Promise((resolve) => (resolveSlow = resolve)))
    metaByRegistry.set(4, Promise.resolve(metaOf(66)))
    const { result } = renderPrefill()

    let slow: Promise<void> = Promise.resolve()
    act(() => {
      result.current.form.setValue('registry_id', 3)
      slow = result.current.prefill(3)
    })
    result.current.form.setValue('registry_id', 4)
    await act(() => result.current.prefill(4))
    await act(async () => {
      resolveSlow(metaOf(55))
      await slow
    })

    expect(result.current.form.getValues('referent_id')).toBe(66)
  })

  it('drops the response when the form no longer holds that anagrafica', async () => {
    metaByRegistry.set(5, Promise.resolve(metaOf(55)))
    const { result } = renderPrefill()
    result.current.form.setValue('registry_id', 9)

    await act(() => result.current.prefill(5))

    expect(result.current.form.getValues('referent_id')).toBeNull()
  })
})
