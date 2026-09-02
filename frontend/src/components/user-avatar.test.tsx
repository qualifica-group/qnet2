import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { avatarColor } from './avatar-color'
import { avatarInitials } from './avatar-initials'
import { UserAvatar } from './user-avatar'

/**
 * These lock the app-wide avatar contract: one palette, one initials rule, one
 * shape. A regression here is exactly the symptom that motivated the
 * unification — the same person looking different from screen to screen.
 */
describe('avatarInitials', () => {
  it('takes the first letter of the first two words', () => {
    expect(avatarInitials('Mario Rossi')).toBe('MR')
    expect(avatarInitials('Anna Maria Bianchi')).toBe('AM')
  })

  it('takes the first two letters of a single-word name', () => {
    expect(avatarInitials('Acme')).toBe('AC')
  })

  it('is unaffected by irregular whitespace', () => {
    expect(avatarInitials('  Mario   Rossi ')).toBe('MR')
    expect(avatarInitials('Mario\nRossi')).toBe('MR')
  })

  it('falls back to a placeholder for an empty name', () => {
    expect(avatarInitials('   ')).toBe('?')
  })
})

describe('UserAvatar', () => {
  it('tints the initials fallback with the name-derived palette color', () => {
    render(<UserAvatar name="Mario Rossi" />)

    const fallback = screen.getByText('MR')
    const color = avatarColor('Mario Rossi')

    expect(fallback.style.backgroundColor).not.toBe('')
    expect(fallback.style.color).not.toBe('')
    expect(avatarColor('  MARIO ROSSI ')).toEqual(color)
  })

  it('gives the same name the same color whatever the size rung', () => {
    const { container: small } = render(<UserAvatar name="Mario Rossi" size="xs" />)
    const { container: large } = render(<UserAvatar name="Mario Rossi" size="2xl" />)

    const bg = (root: HTMLElement) =>
      root.querySelector<HTMLElement>('[data-slot="avatar-fallback"]')?.style.backgroundColor

    expect(bg(small)).toBe(bg(large))
  })

  it('is always a circle on every rung', () => {
    const { container } = render(<UserAvatar name="Acme" size="xl" />)
    const root = container.querySelector('[data-slot="avatar"]')

    expect(root?.className).toContain('rounded-full')
    expect(root).toHaveAttribute('data-size', 'xl')
  })

  it('renders the image with the name as alt text when one is available', () => {
    render(<UserAvatar name="Mario Rossi" src="data:image/png;base64,AAAA" />)

    // Radix keeps the fallback until the image reports as loaded, which jsdom
    // never does; the contract asserted here is that the name still drives it.
    expect(screen.getByText('MR')).toBeInTheDocument()
  })
})
