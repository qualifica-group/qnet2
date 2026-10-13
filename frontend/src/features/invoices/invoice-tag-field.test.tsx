import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { FormProvider, useForm, useWatch } from 'react-hook-form'
import i18n from '@/i18n'
import { InvoiceTagBadge } from '@/features/invoices/invoice-status-badges'
import { InvoiceTagField } from '@/features/invoices/invoice-tag-field'
import type { InvoiceTagRef } from '@/features/invoices/types'

/**
 * Spec 0247 AC-014: the tag is picked from the `invoice-tags` catalog (for-select),
 * "no tag" is the empty state, and the badge takes its colour from the server.
 */

const fetchForSelectMock = vi.fn()

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

const TAGS = [
  { id: 1, label: 'Quote' },
  { id: 2, label: 'Final balance' },
]

const onValue = vi.fn<(value: number | null) => void>()

function Harness({ initial, selected }: { initial: number | null; selected: InvoiceTagRef | null }) {
  const form = useForm<{ invoice_tag_id: number | null }>({ defaultValues: { invoice_tag_id: initial } })
  onValue(useWatch({ control: form.control, name: 'invoice_tag_id' }))
  return (
    <FormProvider {...form}>
      <InvoiceTagField control={form.control} selected={selected} />
    </FormProvider>
  )
}

function renderField(initial: number | null, selected: InvoiceTagRef | null = null) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return render(<Harness initial={initial} selected={selected} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  onValue.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: TAGS,
    export_link: null,
    pagination: { total: 2, offset: 0, limit: 25, total_pages: 1 },
  })
})

describe('InvoiceTagField', () => {
  it('shows "No tag" when empty and reads the options from the invoice-tags catalog', async () => {
    renderField(null)

    const trigger = screen.getByRole('combobox', { name: 'Tag' })
    expect(trigger).toHaveTextContent('No tag')

    fireEvent.click(trigger)
    expect(await screen.findByRole('option', { name: 'Final balance' })).toBeInTheDocument()
    expect(fetchForSelectMock.mock.calls[0][0]).toBe('invoice-tags')
  })

  it('writes the picked tag id into the form', async () => {
    renderField(null)

    fireEvent.click(screen.getByRole('combobox', { name: 'Tag' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Final balance' }))

    await waitFor(() => expect(onValue).toHaveBeenLastCalledWith(2))
  })

  it('shows a saved tag that is no longer in the active list and clears back to no tag', async () => {
    renderField(9, { id: 9, name: 'Retired tag', color: 'slate' })

    expect(screen.getByRole('combobox', { name: 'Tag' })).toHaveTextContent('Retired tag')

    fireEvent.click(screen.getByRole('button', { name: /Clear/i }))
    await waitFor(() => expect(onValue).toHaveBeenLastCalledWith(null))
  })
})

describe('InvoiceTagBadge', () => {
  it('shows the tag name with the colour of the server token', () => {
    render(<InvoiceTagBadge tag={{ id: 1, name: 'Quote', color: 'amber' }} />)

    expect(screen.getByText('Quote').className).toContain('bg-amber-100')
  })

  it('takes a different colour for a different token', () => {
    render(<InvoiceTagBadge tag={{ id: 2, name: 'Final', color: 'teal' }} />)

    expect(screen.getByText('Final').className).toContain('bg-teal-100')
  })
})
