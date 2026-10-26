import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { lazy, type ReactElement } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { FormProvider, useForm, useWatch, type Control, type UseFormReturn } from 'react-hook-form'
import i18n from '@/i18n'
import { ForSelectFormField } from '@/features/commission-settlements/for-select-form-field'
import { InvoiceEditorRelationField } from '@/features/invoices/invoice-editor-relation-field'
import { PurchaseInvoiceEditorRelationField } from '@/features/purchase-invoices/purchase-invoice-editor-relation-field'
import { PurchaseFinancialAccountSelect } from '@/features/purchase-invoice-installments/purchase-financial-account-select'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'

/**
 * "+" next to the raw for-select pickers that are not a `RelationSelectField`
 * (spec 0028 D3): each wrapper must show it for a registered resource when the
 * actor holds `{domain}.create`, hide it without, and write the created id.
 */

const resolveQuickCreateMock = vi.fn<(resource: string) => QuickCreateEntry | null>()
vi.mock('@/features/quick-create/quick-create-registry', () => ({
  resolveQuickCreate: (resource: string) => resolveQuickCreateMock(resource),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn().mockResolvedValue({
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 1 },
  }),
}))

const CREATE_TITLE = 'sources.form.createTitle'

function fakeEntry(): QuickCreateEntry {
  return {
    titleKey: CREATE_TITLE,
    descriptionKey: 'sources.form.createSubtitle',
    permission: 'payment-methods.create',
    form: lazy(async () => ({
      default: ({ onSuccess }: QuickCreateFormProps) => (
        <button type="button" onClick={() => onSuccess({ id: 42, name: 'Bonifico' })}>
          fake-submit
        </button>
      ),
    })),
  }
}

interface HarnessValues {
  payment_method_id: number | null
}

function Harness({ children, values }: { children: (form: UseFormReturn<HarnessValues>) => ReactElement; values: HarnessValues }) {
  const form = useForm<HarnessValues>({ defaultValues: values })
  return (
    <FormProvider {...form}>
      {children(form)}
      <WatchedValues control={form.control} />
    </FormProvider>
  )
}

function WatchedValues({ control }: { control: Control<HarnessValues> }) {
  return <output aria-label="value">{JSON.stringify(useWatch({ control }))}</output>
}

function renderWithClient(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

const createButtonName = () => i18n.t(CREATE_TITLE)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  resolveQuickCreateMock.mockReset().mockReturnValue(fakeEntry())
  canMock.mockReset().mockReturnValue(true)
})

describe('ForSelectFormField', () => {
  const renderField = (quickCreate: boolean) =>
    renderWithClient(
      <Harness values={{ payment_method_id: null }}>
        {(form) => (
          <ForSelectFormField
            control={form.control}
            name="payment_method_id"
            resource="payment-methods"
            label="Payment method"
            placeholder="Pick"
            quickCreate={quickCreate}
          />
        )}
      </Harness>,
    )

  it('shows the "+" when quickCreate is on and the actor can create, and writes the created id', async () => {
    renderField(true)

    fireEvent.click(await screen.findByRole('button', { name: createButtonName() }))
    fireEvent.click(await screen.findByRole('button', { name: 'fake-submit' }))

    await waitFor(() => expect(screen.getByLabelText('value')).toHaveTextContent('"payment_method_id":42'))
  })

  it('hides the "+" without the create permission', () => {
    canMock.mockReturnValue(false)
    renderField(true)

    expect(screen.queryByRole('button', { name: createButtonName() })).not.toBeInTheDocument()
  })

  it('stays a plain picker when quickCreate is off (filter pickers)', () => {
    renderField(false)

    expect(screen.queryByRole('button', { name: createButtonName() })).not.toBeInTheDocument()
  })
})

describe('InvoiceEditorRelationField', () => {
  it('shows the "+" for a permitted resource and hides it without permission', async () => {
    const element = (
      <Harness values={{ payment_method_id: null }}>
        {(form) => (
          <InvoiceEditorRelationField
            control={form.control as never}
            name="payment_method_id"
            resource="payment-methods"
            label="Payment method"
            placeholder="Pick"
            selected={null}
          />
        )}
      </Harness>
    )
    const { unmount } = renderWithClient(element)
    expect(await screen.findByRole('button', { name: createButtonName() })).toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(false)
    renderWithClient(element)
    expect(screen.queryByRole('button', { name: createButtonName() })).not.toBeInTheDocument()
  })
})

describe('PurchaseInvoiceEditorRelationField', () => {
  it('shows the "+" and writes the created id into the form', async () => {
    renderWithClient(
      <Harness values={{ payment_method_id: null }}>
        {(form) => (
          <PurchaseInvoiceEditorRelationField
            control={form.control as never}
            name="payment_method_id"
            resource="payment-methods"
            label="Payment method"
            placeholder="Pick"
            selected={null}
          />
        )}
      </Harness>,
    )

    fireEvent.click(await screen.findByRole('button', { name: createButtonName() }))
    fireEvent.click(await screen.findByRole('button', { name: 'fake-submit' }))

    await waitFor(() => expect(screen.getByLabelText('value')).toHaveTextContent('"payment_method_id":42'))
  })
})

describe('PurchaseFinancialAccountSelect', () => {
  it('shows the "+" and reports the created id through onChange', async () => {
    const onChange = vi.fn()
    renderWithClient(<PurchaseFinancialAccountSelect value={null} onChange={onChange} label="Account" />)

    fireEvent.click(await screen.findByRole('button', { name: createButtonName() }))
    fireEvent.click(await screen.findByRole('button', { name: 'fake-submit' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith(42))
  })

  it('hides the "+" for a resource without a registry entry', () => {
    resolveQuickCreateMock.mockReturnValue(null)
    renderWithClient(<PurchaseFinancialAccountSelect value={null} onChange={vi.fn()} label="Account" />)

    expect(screen.queryByRole('button', { name: createButtonName() })).not.toBeInTheDocument()
  })
})
