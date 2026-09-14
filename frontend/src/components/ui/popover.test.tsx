import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'

import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

describe('Popover', () => {
  it('opens the content when the trigger is clicked', async () => {
    render(
      <Popover>
        <PopoverTrigger>Open</PopoverTrigger>
        <PopoverContent>Popover body</PopoverContent>
      </Popover>
    )

    expect(screen.queryByText('Popover body')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Open' }))

    expect(await screen.findByText('Popover body')).toBeInTheDocument()
  })

  it('renders the content with the design-system token classes', async () => {
    render(
      <Popover>
        <PopoverTrigger>Open</PopoverTrigger>
        <PopoverContent>Popover body</PopoverContent>
      </Popover>
    )

    fireEvent.click(screen.getByRole('button', { name: 'Open' }))
    const content = await screen.findByText('Popover body')

    expect(content).toHaveClass('bg-popover', 'text-popover-foreground', 'border')
  })

  it('closes on Escape and returns focus to the trigger', async () => {
    render(
      <Popover>
        <PopoverTrigger>Open</PopoverTrigger>
        <PopoverContent>Popover body</PopoverContent>
      </Popover>
    )

    const trigger = screen.getByRole('button', { name: 'Open' })
    fireEvent.click(trigger)
    const content = await screen.findByText('Popover body')

    fireEvent.keyDown(content, { key: 'Escape' })

    await waitFor(() => {
      expect(screen.queryByText('Popover body')).not.toBeInTheDocument()
    })
    expect(trigger).toHaveFocus()
  })
})
