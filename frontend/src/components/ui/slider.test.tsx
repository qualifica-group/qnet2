import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'

import { Slider } from '@/components/ui/slider'

describe('Slider', () => {
  it('renders a single named slider for a single value', () => {
    render(<Slider min={0} max={100} step={1} value={[40]} aria-label="Success probability" />)

    const sliders = screen.getAllByRole('slider')
    expect(sliders).toHaveLength(1)
    expect(sliders[0]).toHaveAccessibleName('Success probability')
    expect(sliders[0]).toHaveAttribute('aria-valuenow', '40')
  })

  it('renders two named sliders for a two-value range', () => {
    render(
      <Slider
        min={0}
        max={101}
        step={1}
        value={[20, 80]}
        thumbLabels={['Coverage minimum', 'Coverage maximum']}
      />
    )

    const sliders = screen.getAllByRole('slider')
    expect(sliders).toHaveLength(2)
    expect(sliders[0]).toHaveAccessibleName('Coverage minimum')
    expect(sliders[1]).toHaveAccessibleName('Coverage maximum')
    expect(sliders[0]).toHaveAttribute('aria-valuenow', '20')
    expect(sliders[1]).toHaveAttribute('aria-valuenow', '80')
  })

  it('updates the right handle on ArrowRight for a two-value range', () => {
    const onValueChange = vi.fn()
    render(
      <Slider
        min={0}
        max={101}
        step={1}
        value={[20, 80]}
        onValueChange={onValueChange}
        thumbLabels={['Coverage minimum', 'Coverage maximum']}
      />
    )

    const [, maxThumb] = screen.getAllByRole('slider')
    maxThumb.focus()
    fireEvent.keyDown(maxThumb, { key: 'ArrowRight' })

    expect(onValueChange).toHaveBeenCalledWith([20, 81])
  })

  it('updates the single handle on ArrowRight', () => {
    const onValueChange = vi.fn()
    render(
      <Slider
        min={0}
        max={100}
        step={1}
        value={[40]}
        onValueChange={onValueChange}
        aria-label="Success probability"
      />
    )

    const thumb = screen.getByRole('slider')
    thumb.focus()
    fireEvent.keyDown(thumb, { key: 'ArrowRight' })

    expect(onValueChange).toHaveBeenCalledWith([41])
  })
})
