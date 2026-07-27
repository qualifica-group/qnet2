import { beforeAll, describe, expect, it } from 'vitest'
import { useForm } from 'react-hook-form'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { OpportunityGeneralNotesSection } from '@/features/opportunities/opportunity-general-notes-section'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/**
 * "Note generali" (user directive 2026-07-27): a single free-text field,
 * prefilled from the originating lead's notes and always editable.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function Harness({ initial = null }: { initial?: string | null }) {
  const form = useForm<OpportunityFormValues>({
    defaultValues: { general_notes: initial },
  })
  return (
    <Form {...form}>
      <OpportunityGeneralNotesSection control={form.control} />
    </Form>
  )
}

describe('OpportunityGeneralNotesSection', () => {
  it('hydrates the textarea with the inherited notes and counts their length', () => {
    render(<Harness initial="Inherited from the lead" />)

    expect(screen.getByRole('textbox', { name: /general notes/i })).toHaveValue('Inherited from the lead')
    expect(screen.getByText('23/5000')).toBeInTheDocument()
  })

  it('renders an empty textarea when the opportunity carries no notes', () => {
    render(<Harness />)

    expect(screen.getByRole('textbox', { name: /general notes/i })).toHaveValue('')
    expect(screen.getByText('0/5000')).toBeInTheDocument()
  })

  it('keeps the counter in sync while typing', () => {
    render(<Harness />)

    fireEvent.change(screen.getByRole('textbox', { name: /general notes/i }), {
      target: { value: 'Recall in September' },
    })

    expect(screen.getByText('19/5000')).toBeInTheDocument()
  })
})
