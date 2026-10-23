import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ControlledChoiceDialog } from '@/components/controlled-choice-dialog'

const OPTIONS = [
  { value: 'A', label: 'Option A', description: 'Does A' },
  { value: 'B', label: 'Option B' },
] as const

function renderDialog(props: { dismissible: boolean; onConfirm?: () => void; onOpenChange?: (open: boolean) => void }) {
  render(
    <ControlledChoiceDialog
      open
      title="Pick one"
      summary={<span>3 quotes affected</span>}
      choiceLabel="Recalculation mode"
      options={[...OPTIONS]}
      confirmLabel="Apply"
      dismissible={props.dismissible}
      onConfirm={props.onConfirm ?? vi.fn()}
      onOpenChange={props.onOpenChange ?? vi.fn()}
    />,
  )
}

describe('ControlledChoiceDialog (AC-012)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the summary and the options with none selected, confirm disabled', () => {
    renderDialog({ dismissible: false })

    expect(screen.getByText('3 quotes affected')).toBeInTheDocument()
    expect(screen.getByRole('radiogroup', { name: 'Recalculation mode' })).toBeInTheDocument()
    const radios = screen.getAllByRole('radio')
    expect(radios).toHaveLength(2)
    radios.forEach((radio) => expect(radio).not.toBeChecked())
    expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled()
  })

  it('enables confirm after a choice and confirms with that value', () => {
    const onConfirm = vi.fn()
    renderDialog({ dismissible: false, onConfirm })

    fireEvent.click(screen.getByRole('radio', { name: /Option B/ }))
    const confirm = screen.getByRole('button', { name: 'Apply' })
    expect(confirm).toBeEnabled()
    fireEvent.click(confirm)

    expect(onConfirm).toHaveBeenCalledWith('B')
  })

  it('non-dismissible: no cancel, no close button, Esc ignored', () => {
    const onOpenChange = vi.fn()
    renderDialog({ dismissible: false, onOpenChange })

    expect(screen.queryByRole('button', { name: /Cancel|Close/ })).not.toBeInTheDocument()
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(onOpenChange).not.toHaveBeenCalled()
  })

  it('dismissible: cancel button and Esc call onOpenChange(false)', () => {
    const onOpenChange = vi.fn()
    renderDialog({ dismissible: true, onOpenChange })

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(onOpenChange).toHaveBeenCalledTimes(2)
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })
})
