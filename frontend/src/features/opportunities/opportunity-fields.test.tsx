import { beforeAll, describe, expect, it } from 'vitest'
import { useForm } from 'react-hook-form'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import {
  OpportunityGeneralNotesField,
  OpportunitySuccessProbabilityField,
} from '@/features/opportunities/opportunity-fields'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/**
 * The opportunity's scalar field components, mounted on their own (spec 0198:
 * the same control is the editor of a detail row and of a create-draft row).
 * Ported from the section tests that went with the removed form sections:
 * "Note generali" (user directive 2026-07-27) and the success-probability
 * slider (AC-096).
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function GeneralNotesHarness({ initial = null }: { initial?: string | null }) {
  const form = useForm<OpportunityFormValues>({ defaultValues: { general_notes: initial } })
  return (
    <Form {...form}>
      <OpportunityGeneralNotesField control={form.control} />
    </Form>
  )
}

function ProbabilityHarness({ initial = 0 }: { initial?: number }) {
  const form = useForm<OpportunityFormValues>({ defaultValues: { success_probability: initial } })
  return (
    <Form {...form}>
      <OpportunitySuccessProbabilityField control={form.control} />
    </Form>
  )
}

describe('OpportunityGeneralNotesField', () => {
  it('hydrates the textarea with the inherited notes and counts their length', () => {
    render(<GeneralNotesHarness initial="Inherited from the lead" />)

    expect(screen.getByRole('textbox', { name: /general notes/i })).toHaveValue('Inherited from the lead')
    expect(screen.getByText('23/5000')).toBeInTheDocument()
  })

  it('renders an empty textarea when the opportunity carries no notes', () => {
    render(<GeneralNotesHarness />)

    expect(screen.getByRole('textbox', { name: /general notes/i })).toHaveValue('')
    expect(screen.getByText('0/5000')).toBeInTheDocument()
  })

  it('keeps the counter in sync while typing', () => {
    render(<GeneralNotesHarness />)

    fireEvent.change(screen.getByRole('textbox', { name: /general notes/i }), {
      target: { value: 'Recall in September' },
    })

    expect(screen.getByText('19/5000')).toBeInTheDocument()
  })
})

/** AC-096: success_probability is a 0..100 slider that always holds a value, shown in %. */
describe('OpportunitySuccessProbabilityField (AC-096)', () => {
  it('renders the probability as a 0..100 slider defaulting to 0%', () => {
    render(<ProbabilityHarness />)

    const slider = screen.getByRole('slider', { name: /probability/i })
    expect(slider).toHaveAttribute('aria-valuenow', '0')
    expect(slider).toHaveAttribute('aria-valuemin', '0')
    expect(slider).toHaveAttribute('aria-valuemax', '100')
    expect(screen.getByText('0%')).toBeInTheDocument()
  })

  it('updates the displayed value on keyboard interaction', () => {
    render(<ProbabilityHarness initial={40} />)

    const slider = screen.getByRole('slider', { name: /probability/i })
    expect(screen.getByText('40%')).toBeInTheDocument()

    fireEvent.keyDown(slider, { key: 'ArrowRight' })

    expect(screen.getByText('41%')).toBeInTheDocument()
    expect(slider).toHaveAttribute('aria-valuenow', '41')
  })
})
