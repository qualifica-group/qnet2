import { beforeAll, describe, expect, it, vi } from 'vitest'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { RequestWorkflowStatusField } from '@/features/request-management/request-workflow-status-field'
import {
  buildRequestWorkSchema,
  type RequestWorkFormValues,
  type RequestWorkOriginalState,
} from '@/features/request-management/request-work-schema'
import type { RequestWorkflowStatusRef } from '@/features/request-management/types'

/**
 * Spec 0054 D-5: the panel collects the note the server now requires
 * (`RequestManagementService::updateWork()`) when the picked status is
 * flagged `requires_note` and it's actually a change — never otherwise, so
 * the field never appears for a no-op reselect or a status that doesn't
 * need one.
 */

const STATUSES: RequestWorkflowStatusRef[] = [
  { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
  { id: 101, name: 'Closed', color: 'green', system_key: null, description: null, requires_note: true },
]

/** The loaded panel state the schema decides its sparse rules against. */
function original(workflowStatusId: number): RequestWorkOriginalState {
  return {
    workflow_status_id: workflowStatusId,
    attribute_values: {},
    products_of_interest: [700],
    product_lines: [{ business_function_id: 40, product_category_id: 500 }],
    client_identity: null,
    client_contacts: [],
    client_address: null,
  }
}

function Harness({ onSubmit }: { onSubmit: () => void }) {
  const schema = buildRequestWorkSchema([], STATUSES, original(100), i18n.t)
  const form = useForm<RequestWorkFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      opportunity_workflow_status_id: 100,
      next_callback_at: null,
      note: '',
      client_identity: null,
      client_contacts: [],
      client_address: [],
      products_of_interest: [700],
      product_lines: [{ business_function_id: 40, product_category_id: 500 }],
      rewards: [],
      // Mandatory since the user directive 2026-07-29: a submit-able form
      // always carries a Fonte (this suite is about the status field).
      source_id: 30,
      reporter_id: null,
      operator_id: null,
      operational_site_id: null,
      attribute_values: {},
    },
  })

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)}>
        <RequestWorkflowStatusField control={form.control} statuses={STATUSES} />
        <button type="submit">Submit</button>
      </form>
    </Form>
  )
}

/**
 * The accessible name of a `requires_note` option is the status name run
 * together with the badge text ("ClosedNote required" — no separating
 * whitespace between the two `<span>`s), so a prefix match keeps the
 * lookup readable regardless of the badge.
 */
function pickStatus(name: string) {
  fireEvent.click(screen.getByRole('combobox', { name: 'Working status' }))
  fireEvent.click(screen.getByRole('option', { name: new RegExp(`^${name}`) }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RequestWorkflowStatusField — note requirement (spec 0054 D-5)', () => {
  it('does not render the note field for the loaded, unchanged status', () => {
    render(<Harness onSubmit={vi.fn()} />)

    expect(screen.queryByRole('textbox', { name: 'Note' })).not.toBeInTheDocument()
  })

  it('does not render the note field when picking a status that does not require one', () => {
    render(<Harness onSubmit={vi.fn()} />)

    pickStatus('Open')

    expect(screen.queryByRole('textbox', { name: 'Note' })).not.toBeInTheDocument()
  })

  it('renders the note field once a requires_note status is picked', () => {
    render(<Harness onSubmit={vi.fn()} />)

    pickStatus('Closed')

    expect(screen.getByRole('textbox', { name: 'Note' })).toBeInTheDocument()
  })

  it('hides the note field again once the status is picked back to the loaded one', () => {
    render(<Harness onSubmit={vi.fn()} />)

    pickStatus('Closed')
    expect(screen.getByRole('textbox', { name: 'Note' })).toBeInTheDocument()

    pickStatus('Open')
    expect(screen.queryByRole('textbox', { name: 'Note' })).not.toBeInTheDocument()
  })

  it('blocks submit and surfaces the accessible error triad when the note is left blank', async () => {
    const onSubmit = vi.fn()
    render(<Harness onSubmit={onSubmit} />)

    pickStatus('Closed')
    fireEvent.click(screen.getByRole('button', { name: 'Submit' }))

    const message = await screen.findByText('A note is required to move to this status.')
    expect(message).toHaveAttribute('role', 'alert')

    const noteField = screen.getByRole('textbox', { name: 'Note' })
    expect(noteField).toHaveAttribute('aria-invalid', 'true')
    expect(noteField).toHaveAttribute('aria-describedby', expect.stringContaining(message.id))
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('submits once the note is filled in', async () => {
    const onSubmit = vi.fn()
    render(<Harness onSubmit={onSubmit} />)

    pickStatus('Closed')
    fireEvent.change(screen.getByRole('textbox', { name: 'Note' }), {
      target: { value: 'Client confirmed by phone.' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Submit' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
  })
})
