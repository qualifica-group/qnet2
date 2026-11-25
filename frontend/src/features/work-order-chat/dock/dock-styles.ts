/**
 * The body of the open panel and windows. From `sm` up its height is FIXED,
 * Reezl's rule (viewport-bound, capped), so an empty conversation or a list
 * with no channel keeps the same size instead of shrinking to its content
 * (user directive 2026-11-25). It must NOT be `flex-1` there: in the box's
 * column without a height of its own, `flex: 1 1 0%` overrides `height` with
 * a zero basis and the body collapses to its content (measured 33px). Below
 * `sm` the box is full screen and the body fills it.
 */
export const CHAT_DOCK_BODY_CLASS =
  'flex min-h-0 flex-col border-t border-border max-sm:flex-1 sm:h-[60vh] sm:max-h-[36rem]'

/**
 * A dock box (bar, panel, window): a card hanging from the bottom edge, as low
 * as its header when closed. `bg-card` is a rung ABOVE every page surface
 * (`background` lists, `surface` records), so the dock never melts into the
 * page under it (user directive 2026-11-25); its contents use the tints above
 * the card (`bg-muted`), never `bg-card` again.
 */
export const CHAT_DOCK_BOX_CLASS =
  'flex w-40 flex-col overflow-hidden rounded-t-lg border border-b-0 border-border bg-card text-card-foreground shadow-lg sm:w-60'

/** An open box below `sm` takes the whole screen, like the assistant's window. */
export const CHAT_DOCK_FULL_SCREEN_CLASS = 'max-sm:fixed max-sm:inset-0 max-sm:w-auto max-sm:rounded-none max-sm:border-0'

/**
 * The header band of every box: QNet's brand blue, the colour of the app's
 * sidebar, so the low bars stand out at the bottom of any page.
 */
export const CHAT_DOCK_HEADER_CLASS = 'bg-primary text-primary-foreground'

/** The clickable title of a header: fills the header row, which owns the height. */
export const CHAT_DOCK_TRIGGER_CLASS =
  'flex h-full min-w-0 flex-1 items-center gap-2 px-2.5 text-left text-xs font-semibold outline-none hover:bg-primary-foreground/10 focus-visible:ring-[3px] focus-visible:ring-primary-foreground/50 focus-visible:ring-inset'

/** An icon button on the header band (minimize, close, open...). */
export const CHAT_DOCK_HEADER_BUTTON_CLASS =
  'text-primary-foreground/80 hover:bg-primary-foreground/15 hover:text-primary-foreground dark:hover:bg-primary-foreground/15'

/** Secondary text and icons on the header band. */
export const CHAT_DOCK_HEADER_MUTED_CLASS = 'text-primary-foreground/80'

/** A secondary button on the dock's card body: outline with a tinted fill, never the card's own white. */
export const CHAT_DOCK_SOFT_BUTTON_CLASS = 'bg-muted/60 hover:bg-muted'
