import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestCreateClientSection } from '@/features/request-management/request-create-client-section'
import { completeIdentity } from '@/features/request-management/request-create-form-harness'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import type { ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { EnumOption } from '@/features/config/types'

/**
 * The live duplicate notice on the request create form's NEW-client branch
 * (user directive 2026-10-08). Unit behaviour lives in
 * `identity-duplicates/*.test.tsx`; this suite asserts the wiring: the typed
 * identity and contacts reach the check, and picking an existing anagrafica
 * switches it off.
 */

const checkIdentityDuplicatesMock = vi.fn()

vi.mock('@/features/identity-duplicates/duplicate-check-api', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/identity-duplicates/duplicate-check-api')
  >('@/features/identity-duplicates/duplicate-check-api')
  return {
    ...actual,
    checkIdentityDuplicates: (...args: unknown[]) => checkIdentityDuplicatesMock(...args),
  }
})

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'company', label: 'Company', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  contact_type: [],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <div />,
}))

const PHONE_CONTACT: ContactDraft[] = [
  { _key: 'phone-1', type: 'phone', value: '3331234567', label: null, is_primary: true },
]

const NO_OP = () => undefined

interface HarnessProps {
  identity: PersonalDataDraft
  usingExistingRegistry: boolean
}

function Harness({ identity, usingExistingRegistry }: HarnessProps) {
  const form = useForm<RequestCreateFormValues>()

  return (
    <Form {...form}>
      <RequestCreateClientSection
      control={form.control}
      identity={identity}
      onIdentityChange={NO_OP}
      contacts={PHONE_CONTACT}
      onContactsChange={NO_OP}
      address={[]}
      onAddressChange={NO_OP}
      usingExistingRegistry={usingExistingRegistry}
      revalidateSignal={0}
      errorMessage={null}
      />
    </Form>
  )
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  checkIdentityDuplicatesMock.mockReset()
  checkIdentityDuplicatesMock.mockResolvedValue({ matches: [] })
})

describe('RequestCreateClientSection — duplicate warning', () => {
  it('announces an existing holder of the new client tax code and phone', async () => {
    checkIdentityDuplicatesMock.mockResolvedValue({
      matches: [{ owner_type: 'registry', owner_id: 3, name: 'Mario Rossi', matched_on: ['phone', 'tax_code'] }],
    })

    render(
      <Harness
        identity={{ ...completeIdentity(), tax_code: 'RSSMRA80A01H501U' }}
        usingExistingRegistry={false}
      />,
      { wrapper: wrapper() },
    )

    const status = await screen.findByRole('status')
    expect(status).toHaveTextContent('Mario Rossi')
    expect(checkIdentityDuplicatesMock).toHaveBeenCalledWith({
      tax_code: 'RSSMRA80A01H501U',
      vat_number: undefined,
      contacts: [{ type: 'phone', value: '3331234567' }],
    })
  })

  it('does not check once an existing anagrafica is picked', async () => {
    render(
      <Harness
        identity={{ ...completeIdentity(), tax_code: 'RSSMRA80A01H501U' }}
        usingExistingRegistry
      />,
      { wrapper: wrapper() },
    )

    await new Promise((resolve) => setTimeout(resolve, 350))
    await waitFor(() => expect(checkIdentityDuplicatesMock).not.toHaveBeenCalled())
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })
})
