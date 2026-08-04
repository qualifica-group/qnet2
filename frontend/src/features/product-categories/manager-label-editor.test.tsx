import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ManagerLabelEditor } from '@/features/product-categories/manager-label-editor'
import type { ManagerLabels } from '@/features/product-categories/types'

/**
 * Spec 0080 amendment A1: the row list is DYNAMIC — whatever positions are
 * keys in `value` (blank included), no fixed 1..4 range. "Add level" appends
 * the next free position (disabled at the 12-position ceiling); "Reset" on a
 * row removes only that key. The reasonable-minimum padding on open is the
 * form hook's job (`toManagerLabelsFormValue`, tested in
 * `product-category-form-body.test.tsx` AC-040/041), not this component's.
 * `ManagerLabelsInheritanceToggle` needs an RHF `control` and is covered by
 * the form integration suite instead (matches `InheritanceToggle`, also
 * untested standalone).
 */

const EMPTY: ManagerLabels = {}
const FOUR_ROWS: ManagerLabels = { '1': '', '2': '', '3': '', '4': '' }

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderEditor(value: ManagerLabels = EMPTY, inherited: ManagerLabels = EMPTY, onChange = vi.fn()) {
  render(<ManagerLabelEditor value={value} onChange={onChange} inherited={inherited} />)
  return { onChange }
}

describe('ManagerLabelEditor — rows follow the value keys (spec 0080 A1)', () => {
  it('renders one row per key present in value, sorted, with the default-denomination placeholder (AC-040)', () => {
    renderEditor(FOUR_ROWS)

    for (const n of [1, 2, 3, 4]) {
      const input = screen.getByRole('textbox', { name: `A.M. ${n}` })
      expect(input).toHaveAttribute('placeholder', `Account manager ${n}`)
      expect(input).toHaveValue('')
    }
  })

  it('renders no row at all when value is empty', () => {
    renderEditor()

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('typing in one row only changes that position (AC-042)', () => {
    const { onChange } = renderEditor(FOUR_ROWS)

    fireEvent.change(screen.getByRole('textbox', { name: 'A.M. 2' }), { target: { value: 'Operator' } })

    expect(onChange).toHaveBeenCalledWith({ '1': '', '2': 'Operator', '3': '', '4': '' })
  })
})

describe('ManagerLabelEditor — add/reset a level (spec 0080 A1 AC-054/AC-055)', () => {
  it('"Add level" appends the next free position as a blank row', () => {
    const { onChange } = renderEditor({ '1': 'Commercial', '2': 'Operator' })

    fireEvent.click(screen.getByRole('button', { name: 'Add level' }))

    expect(onChange).toHaveBeenCalledWith({ '1': 'Commercial', '2': 'Operator', '3': '' })
  })

  it('"Add level" fills the smallest free gap rather than always growing past the highest position', () => {
    const { onChange } = renderEditor({ '1': 'Commercial', '3': 'Consultant' })

    fireEvent.click(screen.getByRole('button', { name: 'Add level' }))

    expect(onChange).toHaveBeenCalledWith({ '1': 'Commercial', '3': 'Consultant', '2': '' })
  })

  it('disables "Add level" once all 12 positions are in use', () => {
    const twelve: ManagerLabels = Object.fromEntries(
      Array.from({ length: 12 }, (_, index) => [String(index + 1), '']),
    )
    renderEditor(twelve)

    expect(screen.getByRole('button', { name: 'Add level' })).toBeDisabled()
  })

  it('resetting a row removes ONLY that position\'s key, leaving the others untouched (AC-054)', () => {
    const { onChange } = renderEditor({ '1': 'Commercial', '2': 'Operator', '3': 'Consultant' })

    fireEvent.click(screen.getByRole('button', { name: 'Reset A.M. 2 to the default label' }))

    expect(onChange).toHaveBeenCalledWith({ '1': 'Commercial', '3': 'Consultant' })
  })

  it('hides Add/Reset controls when disabled', () => {
    render(<ManagerLabelEditor value={FOUR_ROWS} onChange={vi.fn()} inherited={EMPTY} disabled />)

    expect(screen.queryByRole('button', { name: 'Add level' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Reset A\.M\./ })).not.toBeInTheDocument()
  })
})

describe('ManagerLabelEditor — inherited preview', () => {
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
