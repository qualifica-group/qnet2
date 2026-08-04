import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ManagerLabelEditor } from '@/features/product-categories/manager-label-editor'
import type { ManagerLabels } from '@/features/product-categories/types'

/**
 * Spec 0080: 4 controlled rows (G.A. 1..4), placeholder = default
 * denomination, read-only inherited preview mirroring the attribute
 * sections' layout. `ManagerLabelsInheritanceToggle` needs an RHF `control`
 * and is covered by the form integration suite instead (matches
 * `InheritanceToggle`, also untested standalone).
 */

const EMPTY: ManagerLabels = {}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderEditor(value: ManagerLabels = EMPTY, inherited: ManagerLabels = EMPTY, onChange = vi.fn()) {
  render(<ManagerLabelEditor value={value} onChange={onChange} inherited={inherited} />)
  return { onChange }
}

describe('ManagerLabelEditor (spec 0080)', () => {
  it('renders one row per G.A. level, placeholder = default denomination (AC-040)', () => {
    renderEditor()

    for (const n of [1, 2, 3, 4]) {
      const input = screen.getByRole('textbox', { name: `A.M. ${n}` })
      expect(input).toHaveAttribute('placeholder', `Account manager ${n}`)
      expect(input).toHaveValue('')
    }
  })

  it('typing in one row only changes that position (AC-042)', () => {
    const { onChange } = renderEditor({ '1': '', '2': '', '3': '', '4': '' })

    fireEvent.change(screen.getByRole('textbox', { name: 'A.M. 2' }), { target: { value: 'Operator' } })

    expect(onChange).toHaveBeenCalledWith({ '1': '', '2': 'Operator', '3': '', '4': '' })
  })

  it('shows the inherited preview only for positions the ancestry resolved', () => {
    renderEditor(EMPTY, { '1': 'Sales rep' })

    const inheritedBlock = screen.getByText('Inherited from ancestor categories').parentElement as HTMLElement
    expect(within(inheritedBlock).getByText('Sales rep')).toBeInTheDocument()
    expect(within(inheritedBlock).getByText('A.M. 1')).toBeInTheDocument()
    expect(within(inheritedBlock).queryByText('A.M. 2')).not.toBeInTheDocument()
  })

  it('renders no inherited block at all when nothing is inherited (AC-041, barrier off)', () => {
    renderEditor()

    expect(screen.queryByText('Inherited from ancestor categories')).not.toBeInTheDocument()
  })

  it('renders the inherit-toggle slot when provided', () => {
    render(
      <ManagerLabelEditor
        value={EMPTY}
        onChange={vi.fn()}
        inherited={EMPTY}
        inheritToggle={<button type="button">toggle-slot</button>}
      />,
    )

    expect(screen.getByRole('button', { name: 'toggle-slot' })).toBeInTheDocument()
  })
})
