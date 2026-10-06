import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { createPortal } from 'react-dom'
import { useOutsidePointerDismiss } from '@/hooks/use-outside-pointer-dismiss'

interface RowProps {
  active: boolean
  onDismiss: () => void
}

/** A row whose "picker" is portalled to `body`, like a Radix popover. */
function Row({ active, onDismiss }: RowProps) {
  const outsidePointer = useOutsidePointerDismiss(active, onDismiss)
  return (
    <div onPointerDownCapture={outsidePointer.onPointerDownCapture}>
      <button type="button">inside</button>
      {createPortal(<button type="button">portalled option</button>, document.body)}
    </div>
  )
}

function renderRow(active: boolean) {
  const onDismiss = vi.fn()
  const view = render(
    <>
      <Row active={active} onDismiss={onDismiss} />
      <button type="button">elsewhere</button>
    </>,
  )
  return { onDismiss, ...view }
}

describe('useOutsidePointerDismiss', () => {
  it('dismisses on a press outside the row', () => {
    const { onDismiss } = renderRow(true)

    fireEvent.pointerDown(screen.getByRole('button', { name: 'elsewhere' }))

    expect(onDismiss).toHaveBeenCalledOnce()
  })

  it('keeps the row on a press inside it, portalled content included', () => {
    const { onDismiss } = renderRow(true)

    fireEvent.pointerDown(screen.getByRole('button', { name: 'inside' }))
    fireEvent.pointerDown(screen.getByRole('button', { name: 'portalled option' }))

    expect(onDismiss).not.toHaveBeenCalled()
  })

  it('keeps the row while a confirmation it asked for is answered', () => {
    const { onDismiss } = renderRow(true)
    render(
      <div role="alertdialog">
        <button type="button">replace</button>
      </div>,
    )

    fireEvent.pointerDown(screen.getByRole('button', { name: 'replace' }))

    expect(onDismiss).not.toHaveBeenCalled()
  })

  it('does nothing while inactive', () => {
    const { onDismiss } = renderRow(false)

    fireEvent.pointerDown(screen.getByRole('button', { name: 'elsewhere' }))

    expect(onDismiss).not.toHaveBeenCalled()
  })
})
