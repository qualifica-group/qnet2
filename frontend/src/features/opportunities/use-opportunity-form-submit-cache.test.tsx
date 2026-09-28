import { beforeEach, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { forSelectKeys } from '@/features/for-select/query-keys'
import {
  useOpportunityForm,
  useOpportunityFormSubmit,
  type OpportunityFormValues,
} from '@/features/opportunities/use-opportunity-form'
import { createValues, original } from '@/features/opportunities/opportunity-form-payload-fixtures'
import type { OpportunityFormMode } from '@/features/opportunities/types'

/**
 * User report 2026-09-28: an Offerta created right after saving its
 * Opportunita' (no page reload) copied a stale team. The Offerta form reads the
 * roles and team from the opportunity for-select `meta`, so a save must drop
 * that cache — not merely mark it stale, which would still serve the old meta
 * first.
 */

const createOpportunityMock = vi.fn()
const updateOpportunityMock = vi.fn()

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    createOpportunity: (...args: unknown[]) => createOpportunityMock(...args),
    updateOpportunity: (...args: unknown[]) => updateOpportunityMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const SAVED = original({ id: 12 })

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const VALUES: OpportunityFormValues = createValues({ manager_slots: [null, 7] })

const OPPORTUNITY_LABELS_KEY = forSelectKeys.labels('opportunities', [12])
const USER_LABELS_KEY = forSelectKeys.labels('users', [7])
const STALE_PAGE = { items: [{ id: 12, label: 'OPP_12', meta: { managers: [] } }] }

function renderSubmit(client: QueryClient, mode: OpportunityFormMode) {
  return renderHook(
    () => {
      const { form } = useOpportunityForm({ mode })
      return useOpportunityFormSubmit({
        form,
        mode,
        leadSubmission: { blocked: false, fromLead: null },
        onSuccess: vi.fn(),
      })
    },
    {
      wrapper: ({ children }: { children: ReactNode }) => (
        <QueryClientProvider client={client}>{children}</QueryClientProvider>
      ),
    },
  )
}

function seededClient(): QueryClient {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(OPPORTUNITY_LABELS_KEY, STALE_PAGE)
  client.setQueryData(USER_LABELS_KEY, STALE_PAGE)
  return client
}

beforeEach(() => {
  createOpportunityMock.mockReset().mockResolvedValue(SAVED)
  updateOpportunityMock.mockReset().mockResolvedValue(SAVED)
})

it('drops the cached opportunity for-select meta after a create', async () => {
  const client = seededClient()
  const { result } = renderSubmit(client, { type: 'create' })

  await act(async () => {
    await result.current.onSubmit(VALUES)
  })

  expect(client.getQueryData(OPPORTUNITY_LABELS_KEY)).toBeUndefined()
  expect(client.getQueryData(USER_LABELS_KEY)).toEqual(STALE_PAGE)
})

it('drops the cached opportunity for-select meta after an update', async () => {
  const client = seededClient()
  const { result } = renderSubmit(client, { type: 'edit', opportunity: { ...SAVED, permissions: FULL_PERMISSIONS } })

  await act(async () => {
    await result.current.onSubmit(VALUES)
  })

  expect(updateOpportunityMock).toHaveBeenCalled()
  expect(client.getQueryData(OPPORTUNITY_LABELS_KEY)).toBeUndefined()
})
