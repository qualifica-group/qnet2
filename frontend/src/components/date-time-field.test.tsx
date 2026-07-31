import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { DateTimeField } from '@/components/date-time-field'

/**
 * User directive 2026-07-31: a planned instant must not force an hour. The
 * split/join rules themselves are covered in `lib/formatting/wire-instant`;
 * what is asserted here is the control built on them.
 */

const DATE_LABEL = 'Callback date'
const TIME_LABEL = 'Callback time (optional)'

function renderField(value: string | null) {
  const onChange = vi.fn()
  render(<DateTimeField value={value} onChange={onChange} dateLabel={DATE_LABEL} timeLabel={TIME_LABEL} />)

  return {
    onChange,
    date: screen.getByLabelText(DATE_LABEL),
    time: screen.getByLabelText(TIME_LABEL),
  }
}

describe('DateTimeField', () => {
  it('renders the two parts of the current value', () => {
    const { date, time } = renderField('2026-08-03T15:30')

    expect(date).toHaveAttribute('type', 'date')
    expect(date).toHaveValue('2026-08-03')
    expect(time).toHaveAttribute('type', 'time')
    expect(time).toHaveValue('15:30')
  })

  it('leaves the time blank for an instant saved without one', () => {
    const { time } = renderField('2026-08-03T00:00')

    expect(time).toHaveValue('')
  })

  it('commits midnight when only the date is picked — the time is optional', () => {
    const { date, onChange } = renderField(null)

    fireEvent.change(date, { target: { value: '2026-08-03' } })

    expect(onChange).toHaveBeenCalledWith('2026-08-03T00:00')
  })

  it('commits the composed instant when the time is picked afterwards', () => {
    const { time, onChange } = renderField('2026-08-03T00:00')

    fireEvent.change(time, { target: { value: '15:30' } })

    expect(onChange).toHaveBeenCalledWith('2026-08-03T15:30')
  })

  it('keeps the time when the date is changed', () => {
    const { date, onChange } = renderField('2026-08-03T15:30')

    fireEvent.change(date, { target: { value: '2026-08-05' } })

    expect(onChange).toHaveBeenCalledWith('2026-08-05T15:30')
  })

  it('commits null when the date is cleared', () => {
    const { date, onChange } = renderField('2026-08-03T15:30')

    fireEvent.change(date, { target: { value: '' } })

    expect(onChange).toHaveBeenCalledWith(null)
  })

  it('locks the time until a date exists, so a typed hour cannot silently vanish', () => {
    const { time } = renderField(null)

    expect(time).toBeDisabled()
  })

  it('disables both inputs when the field is disabled', () => {
    render(
      <DateTimeField
        value="2026-08-03T15:30"
        onChange={vi.fn()}
        dateLabel={DATE_LABEL}
        timeLabel={TIME_LABEL}
        disabled
      />,
    )

    expect(screen.getByLabelText(DATE_LABEL)).toBeDisabled()
    expect(screen.getByLabelText(TIME_LABEL)).toBeDisabled()
  })

  it('forwards slot-injected props (id, aria-describedby) to the date input, the one an external label names', () => {
    render(
      <DateTimeField
        value={null}
        onChange={vi.fn()}
        timeLabel={TIME_LABEL}
        id="callback-form-item"
        aria-describedby="callback-form-item-description"
      />,
    )

    const date = screen.getByLabelText(TIME_LABEL).parentElement?.querySelector('input[type="date"]')
    expect(date).toHaveAttribute('id', 'callback-form-item')
    expect(date).toHaveAttribute('aria-describedby', 'callback-form-item-description')
  })
})
