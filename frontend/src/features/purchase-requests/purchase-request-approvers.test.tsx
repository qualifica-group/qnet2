import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { PurchaseRequestApproverField } from '@/features/purchase-requests/purchase-request-approver-field'
import { makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'
import { toResourcePermissions } from '@/features/purchase-requests/purchase-request-permissions'
import { requestRefs, type RequestRefs } from '@/features/purchase-requests/purchase-request-refs'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import type { ApproverOptions } from '@/features/purchase-requests/types'

const fetchApproverOptionsMock = vi.fn<(requesterId: number, businessFunctionId: number | null) => Promise<ApproverOptions>>()
vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  fetchApproverOptions: (requesterId: number, businessFunctionId: number | null) =>
    fetchApproverOptionsMock(requesterId, businessFunctionId),
}))

const ANNA = { id: 4, name: 'Anna Verdi' }
const LUCA = { id: 9, name: 'Luca Neri' }
const NO_REFS = requestRefs(undefined)

interface HarnessProps {
  requesterId: number | null
  managerId: number | null
  refs?: RequestRefs
}

function Harness({ requesterId, managerId, refs = NO_REFS }: HarnessProps) {
  const form = useForm<PurchaseRequestFormValues>({
    defaultValues: { requester_id: requesterId, function_manager_id: managerId, business_function_id: 6 } as PurchaseRequestFormValues,
  })
  return (
    <Form {...form}>
      <PurchaseRequestApproverField control={form.control} setValue={form.setValue} refs={refs} />
      <button type="button" onClick={() => form.setValue('requester_id', 5)}>
        change requester
      </button>
    </Form>
  )
}

function renderHarness(props: HarnessProps) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ResourcePermissionsProvider permissions={toResourcePermissions(makeRequest())}>
        <Harness {...props} />
      </ResourcePermissionsProvider>
    </QueryClientProvider>,
  )
}

const trigger = () => screen.getByRole('combobox', { name: /Function manager/ })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchApproverOptionsMock.mockReset()
})

describe('PurchaseRequestApproverField (AC-011)', () => {
  it('is disabled until a requester is chosen', () => {
    renderHarness({ requesterId: null, managerId: null })

    expect(trigger()).toBeDisabled()
    expect(screen.getByText('Select the requester first.')).toBeInTheDocument()
    expect(fetchApproverOptionsMock).not.toHaveBeenCalled()
  })

  it('lists only the approvers returned for the requester', async () => {
    fetchApproverOptionsMock.mockResolvedValue({ source: 'reports_to', approvers: [ANNA, LUCA] })
    renderHarness({ requesterId: 3, managerId: null })

    await waitFor(() => expect(trigger()).toBeEnabled())
    fireEvent.click(trigger())

    expect(await screen.findByRole('option', { name: 'Anna Verdi' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Luca Neri' })).toBeInTheDocument()
    expect(screen.getAllByRole('option')).toHaveLength(2)
    expect(fetchApproverOptionsMock).toHaveBeenCalledWith(3, 6)
  })

  it('preselects the only approver', async () => {
    fetchApproverOptionsMock.mockResolvedValue({ source: 'reports_to', approvers: [ANNA] })
    renderHarness({ requesterId: 3, managerId: null })

    await waitFor(() => expect(trigger()).toHaveTextContent('Anna Verdi'))
  })

  it('clears a manager that is not allowed any more after the requester changes', async () => {
    fetchApproverOptionsMock.mockImplementation(async (requesterId) =>
      requesterId === 3
        ? { source: 'reports_to', approvers: [ANNA, LUCA] }
        : { source: 'reports_to', approvers: [{ id: 11, name: 'Elena Gialli' }, { id: 12, name: 'Paolo Blu' }] },
    )
    renderHarness({ requesterId: 3, managerId: 9 })
    await waitFor(() => expect(trigger()).toHaveTextContent('Luca Neri'))

    fireEvent.click(screen.getByRole('button', { name: 'change requester' }))

    await waitFor(() => expect(trigger()).toHaveTextContent('Select...'))
  })

  it('shows the explanation when nobody can approve', async () => {
    fetchApproverOptionsMock.mockResolvedValue({ source: 'none', approvers: [] })
    renderHarness({ requesterId: 3, managerId: null })

    expect(await screen.findByText(/no manager in their profile/)).toBeInTheDocument()
  })

  it('keeps a saved out-of-rule manager while requester and function are unchanged (D-3)', async () => {
    fetchApproverOptionsMock.mockResolvedValue({ source: 'reports_to', approvers: [LUCA] })
    const request = makeRequest({
      requester: { id: 3, name: 'Mario Rossi' },
      function_manager: ANNA,
      business_function: { id: 6, name: 'IT' },
    })
    renderHarness({ requesterId: 3, managerId: 4, refs: requestRefs(request) })

    await waitFor(() => expect(fetchApproverOptionsMock).toHaveBeenCalled())
    await waitFor(() => expect(trigger()).toBeEnabled())
    expect(trigger()).toHaveTextContent('Anna Verdi')

    fireEvent.click(screen.getByRole('button', { name: 'change requester' }))
    await waitFor(() => expect(trigger()).not.toHaveTextContent('Anna Verdi'))
  })
})
