import { cn } from '@/lib/utils'

/**
 * The one look of the "Completa" check before a task's title, shared by the
 * grid's title cell and the detail's sub-task rows (user directive
 * 2026-10-06): a done task shows the icon filled in green, a completable one
 * a muted icon that turns green on hover, anything else a faded icon that
 * only keeps the titles aligned.
 */

export const COMPLETE_ICON_CLASS = 'size-4 shrink-0'

/** Filled green disc with the check cut out of it (the stroke takes the card color). */
export const COMPLETED_ICON_CLASS = 'fill-success text-card'

/** The icon inside a completable toggle: a tinted fill shows up on hover. */
export const COMPLETABLE_ICON_CLASS = 'fill-transparent transition-[fill] group-hover/complete:fill-success/15'

/** Neither done nor completable by this actor. */
export const INERT_ICON_CLASS = 'text-muted-foreground/40'

/**
 * Reads as clickable: hand cursor, a tinted fill and a small pop on hover,
 * a press-in on click (motion only when the user has not asked to reduce it).
 */
export const COMPLETE_TOGGLE_CLASS = cn(
  'group/complete cursor-pointer rounded-full text-muted-foreground outline-none transition-[color,transform] duration-150',
  'hover:text-success focus-visible:text-success focus-visible:ring-2 focus-visible:ring-ring',
  'motion-safe:hover:scale-125 motion-safe:active:scale-95',
)
