/**
 * Category names (deep tree paths, indented) outgrow a product-line row's
 * select column, which splits the row three ways: the popup takes the same
 * wider width as the offer-row product picker — never narrower than the
 * trigger, never past the viewport edge.
 */
export const CATEGORY_POPUP_WIDTH_CLASS =
  'w-[min(36rem,var(--radix-popover-content-available-width))] min-w-(--radix-popover-trigger-width)'
