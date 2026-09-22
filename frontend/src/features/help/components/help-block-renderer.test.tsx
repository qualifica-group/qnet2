import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import '@/i18n'
import { HelpBlockRenderer } from '@/features/help/components/help-block-renderer'
import type { HelpBlock } from '@/features/help/types'

function renderBlock(block: HelpBlock) {
  return render(<HelpBlockRenderer block={block} />)
}

describe('HelpBlockRenderer (AC-008)', () => {
  it('renders steps as an ordered list', () => {
    renderBlock({ type: 'steps', items: ['Primo', 'Secondo'] })
    const list = screen.getByRole('list')
    expect(list.tagName).toBe('OL')
    expect(screen.getAllByRole('listitem').map((item) => item.textContent)).toEqual(['Primo', 'Secondo'])
  })

  it('renders list as an unordered list', () => {
    renderBlock({ type: 'list', items: ['Uno', 'Due'] })
    expect(screen.getByRole('list').tagName).toBe('UL')
  })

  it('renders table with <th> headers inside an overflow-x-auto container', () => {
    const { container } = renderBlock({
      type: 'table',
      headers: ['Campo', 'Valore'],
      rows: [['Nome', 'Mario']],
    })
    expect(screen.getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Campo', 'Valore'])
    expect(container.querySelector('.overflow-x-auto table')).not.toBeNull()
  })

  it('renders tip/warning/note with a textual label, not colour alone', () => {
    renderBlock({ type: 'tip', text: 'Usa i filtri.' })
    expect(screen.getByText('Suggerimento:')).toBeInTheDocument()

    renderBlock({ type: 'warning', text: 'Azione irreversibile.' })
    expect(screen.getByText('Attenzione:')).toBeInTheDocument()

    renderBlock({ type: 'note', text: 'Modulo in sviluppo.' })
    expect(screen.getByText('Nota:')).toBeInTheDocument()
  })

  it('renders **x** as <strong>', () => {
    renderBlock({ type: 'paragraph', text: 'Campo **obbligatorio** da compilare.' })
    const strong = screen.getByText('obbligatorio')
    expect(strong.tagName).toBe('STRONG')
  })

  it('shows a literal "<script>" as text, never as an element', () => {
    const { container } = renderBlock({ type: 'paragraph', text: 'Non incollare <script>alert(1)</script>.' })
    expect(container.querySelector('script')).toBeNull()
    expect(container.textContent).toContain('<script>alert(1)</script>')
  })
})
