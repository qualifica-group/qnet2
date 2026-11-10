import type { CardPlacement } from '@/features/onboarding/types'

/** Space kept between the card and the viewport edges. */
export const VIEWPORT_MARGIN = 12
/** Distance between the highlighted target and the card. */
export const CARD_GAP = 14
/** Breathing room of the spotlight cut-out around the target. */
export const SPOTLIGHT_PADDING = 6

export interface Box {
  top: number
  left: number
  width: number
  height: number
}

export interface Size {
  width: number
  height: number
}

export interface CardPosition {
  top: number
  left: number
}

const FALLBACK_ORDER: Record<CardPlacement, readonly CardPlacement[]> = {
  bottom: ['bottom', 'top', 'right', 'left'],
  top: ['top', 'bottom', 'left', 'right'],
  right: ['right', 'bottom', 'top', 'left'],
  left: ['left', 'bottom', 'top', 'right'],
}

/** The spotlight rectangle: the target grown by the padding on every side. */
export function spotlightBox(target: Box): Box {
  return {
    top: target.top - SPOTLIGHT_PADDING,
    left: target.left - SPOTLIGHT_PADDING,
    width: target.width + SPOTLIGHT_PADDING * 2,
    height: target.height + SPOTLIGHT_PADDING * 2,
  }
}

/** Card centred in the viewport: intro/outro steps and targets that are not on screen. */
export function centeredPosition(card: Size, viewport: Size): CardPosition {
  return {
    top: clamp((viewport.height - card.height) / 2, VIEWPORT_MARGIN, viewport.height - card.height - VIEWPORT_MARGIN),
    left: clamp((viewport.width - card.width) / 2, VIEWPORT_MARGIN, viewport.width - card.width - VIEWPORT_MARGIN),
  }
}

/**
 * Places the card next to the target: the preferred side first, then the
 * fallbacks, picking the first side with enough free room. When no side fits
 * (a tall target on a phone) the card docks at the bottom of the viewport. The
 * result is always clamped inside the viewport, so the card is never cut off.
 */
export function computeCardPosition(
  target: Box,
  card: Size,
  viewport: Size,
  preferred: CardPlacement,
): CardPosition {
  const spot = spotlightBox(target)
  const placement = FALLBACK_ORDER[preferred].find((side) => fits(side, spot, card, viewport))

  const raw = placement
    ? positionOn(placement, spot, card)
    : { top: viewport.height - card.height - VIEWPORT_MARGIN, left: (viewport.width - card.width) / 2 }

  return {
    top: clamp(raw.top, VIEWPORT_MARGIN, viewport.height - card.height - VIEWPORT_MARGIN),
    left: clamp(raw.left, VIEWPORT_MARGIN, viewport.width - card.width - VIEWPORT_MARGIN),
  }
}

function fits(side: CardPlacement, spot: Box, card: Size, viewport: Size): boolean {
  const needed = (side === 'top' || side === 'bottom' ? card.height : card.width) + CARD_GAP + VIEWPORT_MARGIN
  switch (side) {
    case 'bottom':
      return viewport.height - (spot.top + spot.height) >= needed
    case 'top':
      return spot.top >= needed
    case 'right':
      return viewport.width - (spot.left + spot.width) >= needed
    case 'left':
      return spot.left >= needed
  }
}

function positionOn(side: CardPlacement, spot: Box, card: Size): CardPosition {
  const centerX = spot.left + spot.width / 2 - card.width / 2
  const centerY = spot.top + spot.height / 2 - card.height / 2
  switch (side) {
    case 'bottom':
      return { top: spot.top + spot.height + CARD_GAP, left: centerX }
    case 'top':
      return { top: spot.top - card.height - CARD_GAP, left: centerX }
    case 'right':
      return { top: centerY, left: spot.left + spot.width + CARD_GAP }
    case 'left':
      return { top: centerY, left: spot.left - card.width - CARD_GAP }
  }
}

// A card larger than the viewport pins to the margin instead of going negative.
function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(value, Math.max(min, max)))
}
