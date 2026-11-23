import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { EnumFieldControl } from '@/features/custom-fields/components/enum-field-control'
import type { CustomFieldDescriptor, CustomFieldValue } from '@/features/custom-fields/types'

/** Spec 0424 D-1 / AC-005: the `checklist` display, one checkbox per option, writing the list of ticked values. */

const DESCRIPTOR = {
  key: 'groups',
  label: 'Norme richieste',
  type: 'enum',
  config: { display: 'checklist' },
  options: [
    { value: 'general', label: 'Standard 9001', color: null, icon: null },
    { value: 'food', label: 'Standard BRC', color: null, icon: null },
    { value: 'energy', label: 'Standard 50001', color: null, icon: null },
  ],
} as unknown as CustomFieldDescriptor

function Harness({ initial, onChange }: { initial: CustomFieldValue; onChange: (value: CustomFieldValue) => void }) {
  const [value, setValue] = useState<CustomFieldValue>(initial)
  return (
    <EnumFieldControl
      descriptor={DESCRIPTOR}
      value={value}
      onChange={(next) => {
        setValue(next)
        onChange(next)
      }}
      disabled={false}
      readOnly={false}
      id="groups"
      describedBy="groups-error"
      invalid={false}
    />
  )
}

describe('EnumFieldControl — checklist', () => {
  it('renders one checkbox per option, ticked from the stored list', () => {
    render(<Harness initial={['food']} onChange={vi.fn()} />)

    expect(screen.getByRole('group', { name: 'Norme richieste' })).toBeInTheDocument()
    expect(screen.getAllByRole('checkbox')).toHaveLength(3)
    expect(screen.getByRole('checkbox', { name: 'Standard BRC' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Standard 9001' })).not.toBeChecked()
  })

  it('ticks several entries at once, in the options order, and unticks one', () => {
    const onChange = vi.fn()
    render(<Harness initial={null} onChange={onChange} />)

    fireEvent.click(screen.getByRole('checkbox', { name: 'Standard 50001' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Standard 9001' }))
    expect(onChange).toHaveBeenLastCalledWith(['general', 'energy'])

    fireEvent.click(screen.getByRole('checkbox', { name: 'Standard 50001' }))
    expect(onChange).toHaveBeenLastCalledWith(['general'])
  })
})
