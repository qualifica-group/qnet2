import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { RecordFormBody } from '@/components/record-form/record-form-body'

describe('RecordFormBody', () => {
  it('lays the side column out only when one is given', () => {
    const { container, rerender } = render(<RecordFormBody side={<p>side</p>}>main</RecordFormBody>)

    expect(container.firstElementChild?.children).toHaveLength(2)

    rerender(<RecordFormBody side={null}>main</RecordFormBody>)

    expect(container.firstElementChild?.children).toHaveLength(1)
    expect(screen.queryByText('side')).not.toBeInTheDocument()
  })
})
