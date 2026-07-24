import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { ConfigSection } from '@/components/ui/config-section'

/**
 * Spec 0062 MT-2.2. Collapse behaviour/a11y is entirely `FormSection`'s
 * `Collapsible` (already covered by `components/form-section.test.tsx`);
 * these tests only check the additions `ConfigSection` makes on top of it:
 * the frozen `variant` styling and the `defaultCollapsed` -> `defaultOpen`
 * inversion.
 */

describe('ConfigSection', () => {
  it('renders the title, description and children', () => {
    render(
      <ConfigSection title="Identification" description="Primary fields" variant="default">
        <p>field</p>
      </ConfigSection>,
    )

    expect(screen.getByRole('heading', { name: 'Identification' })).toBeInTheDocument()
    expect(screen.getByText('Primary fields')).toBeInTheDocument()
    expect(screen.getByText('field')).toBeInTheDocument()
  })

  it('is not collapsible by default (no toggle button)', () => {
    render(
      <ConfigSection title="Identification" variant="default">
        <p>field</p>
      </ConfigSection>,
    )

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it.each([
    ['default', 'bg-card'],
    ['highlighted', 'bg-primary/5'],
    ['informative', 'bg-muted/40'],
    ['secondary', 'bg-transparent'],
  ] as const)('variant=%s applies a distinct token-based surface class', (variant, expectedClass) => {
    render(
      <ConfigSection title="Identification" variant={variant}>
        <p>field</p>
      </ConfigSection>,
    )

    const section = screen.getByRole('heading', { name: 'Identification' }).closest('section')
    expect(section).not.toBeNull()
    expect(section).toHaveClass(expectedClass)
  })

  it('collapsible + defaultCollapsed=false starts open and collapses on click (a11y preserved)', () => {
    render(
      <ConfigSection title="Advanced" variant="default" collapsible>
        <p>field</p>
      </ConfigSection>,
    )

    expect(screen.getByText('field')).toBeInTheDocument()
    const trigger = screen.getByRole('button', { name: 'Advanced' })
    expect(trigger).toHaveAttribute('data-state', 'open')

    fireEvent.click(trigger)

    expect(trigger).toHaveAttribute('data-state', 'closed')
    expect(screen.queryByText('field')).not.toBeInTheDocument()
  })

  it('collapsible + defaultCollapsed=true starts closed and opens on click', () => {
    render(
      <ConfigSection title="Advanced" variant="default" collapsible defaultCollapsed>
        <p>field</p>
      </ConfigSection>,
    )

    expect(screen.queryByText('field')).not.toBeInTheDocument()
    const trigger = screen.getByRole('button', { name: 'Advanced' })
    expect(trigger).toHaveAttribute('data-state', 'closed')
    expect(trigger).toHaveAttribute('aria-expanded', 'false')

    fireEvent.click(trigger)

    expect(trigger).toHaveAttribute('data-state', 'open')
    expect(trigger).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByText('field')).toBeInTheDocument()
  })
})
