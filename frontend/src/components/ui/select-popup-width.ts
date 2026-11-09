/**
 * A select popup grows to its longest option instead of truncating it, up to a
 * cap that keeps one very long label from spanning the whole screen. Past the
 * cap the option rows ellipsize as before. Each popup adds its own `min-w-*`
 * floor, so a list of short labels stays as compact as it always was.
 */
export const SELECT_POPUP_FIT_CONTENT_CLASS = 'w-max max-w-[min(48rem,calc(100vw-2rem))]'

/** The Radix Popover flavour: never narrower than its trigger. A caller's own `w-*`/`min-w-*` still wins. */
export const SELECT_POPOVER_WIDTH_CLASS = `${SELECT_POPUP_FIT_CONTENT_CLASS} min-w-[max(15rem,var(--radix-popover-trigger-width))]`
