import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RecordCardHeader } from '@/components/detail/record-panel'

/**
 * The legacy id badge beside a record title: rendered from the `old_id` the
 * API sends (only to an actor with `legacy-ids.view`), absent otherwise.
 */
describe('RecordCardHeader legacy id badge', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('it')
  })

  it('shows "OLD ID #<id>" next to the title when the record has a legacy id', () => {
    render(<RecordCardHeader title="Acme S.p.A." legacyId={123} />)

    expect(screen.getByRole('heading', { name: 'Acme S.p.A.' })).toBeInTheDocument()
    expect(screen.getByText('OLD ID #123')).toBeInTheDocument()
  })

  it('renders no badge when the legacy id is missing or null', () => {
    const { rerender } = render(<RecordCardHeader title="Acme S.p.A." />)
    expect(screen.queryByText(/OLD ID/)).not.toBeInTheDocument()

    rerender(<RecordCardHeader title="Acme S.p.A." legacyId={null} />)
    expect(screen.queryByText(/OLD ID/)).not.toBeInTheDocument()
  })
})
