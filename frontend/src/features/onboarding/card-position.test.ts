import { describe, expect, it } from 'vitest'
import {
  CARD_GAP,
  SPOTLIGHT_PADDING,
  VIEWPORT_MARGIN,
  centeredPosition,
  computeCardPosition,
} from '@/features/onboarding/card-position'

const DESKTOP = { width: 1280, height: 800 }
const PHONE = { width: 375, height: 667 }
const CARD = { width: 320, height: 180 }

describe('computeCardPosition (spec 0368 AC-017)', () => {
  it('puts the card below a header icon on the preferred side', () => {
    const target = { top: 10, left: 900, width: 32, height: 32 }
    const position = computeCardPosition(target, CARD, DESKTOP, 'bottom')

    expect(position.top).toBe(10 + 32 + SPOTLIGHT_PADDING + CARD_GAP)
    expect(position.left).toBe(900 + 16 - 160)
  })

  it('puts the card to the right of the side menu', () => {
    const target = { top: 60, left: 0, width: 256, height: 600 }
    const position = computeCardPosition(target, CARD, DESKTOP, 'right')

    expect(position.left).toBe(256 + SPOTLIGHT_PADDING + CARD_GAP)
  })

  it('falls back to the other side when the preferred one has no room', () => {
    const target = { top: 740, left: 1200, width: 48, height: 48 }
    const position = computeCardPosition(target, CARD, DESKTOP, 'bottom')

    expect(position.top + CARD.height).toBeLessThanOrEqual(740 - SPOTLIGHT_PADDING)
  })

  it('docks inside the viewport when no side fits (tall menu on a phone)', () => {
    const target = { top: 0, left: 0, width: 288, height: 667 }
    const position = computeCardPosition(target, CARD, PHONE, 'right')

    expect(position.top).toBe(PHONE.height - CARD.height - VIEWPORT_MARGIN)
    expect(position.left).toBeGreaterThanOrEqual(VIEWPORT_MARGIN)
    expect(position.left + CARD.width).toBeLessThanOrEqual(PHONE.width - VIEWPORT_MARGIN)
  })

  it('never lets the card leave the viewport horizontally', () => {
    const target = { top: 8, left: 340, width: 32, height: 32 }
    const position = computeCardPosition(target, CARD, PHONE, 'bottom')

    expect(position.left + CARD.width).toBeLessThanOrEqual(PHONE.width - VIEWPORT_MARGIN)
  })
})

describe('centeredPosition', () => {
  it('centres the card and pins an oversized card to the margin', () => {
    expect(centeredPosition(CARD, DESKTOP)).toEqual({ top: 310, left: 480 })
    expect(centeredPosition({ width: 500, height: 900 }, PHONE)).toEqual({
      top: VIEWPORT_MARGIN,
      left: VIEWPORT_MARGIN,
    })
  })
})
