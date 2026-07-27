import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RequestGeneralNotesCallout } from '@/features/request-management/request-general-notes-callout'

/**
 * User directive 2026-07-27: the opportunity's "Note generali" are surfaced
 * read-only and highlighted at the top of the work panel's side column.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RequestGeneralNotesCallout', () => {
  it('renders the notes under a labelled region', () => {
    render(<RequestGeneralNotesCallout notes="Recall the client in September" />)

    const region = screen.getByRole('region', { name: /general notes/i })
    expect(region).toHaveTextContent('Recall the client in September')
  })

  it('never renders an edit control — the field is read-only in this module', () => {
    render(<RequestGeneralNotesCallout notes="Recall the client in September" />)

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('renders nothing when there are no notes', () => {
    const { container } = render(<RequestGeneralNotesCallout notes={null} />)

    expect(container).toBeEmptyDOMElement()
  })

  it('renders nothing for an empty string rather than an empty box', () => {
    const { container } = render(<RequestGeneralNotesCallout notes="" />)

    expect(container).toBeEmptyDOMElement()
  })
})
