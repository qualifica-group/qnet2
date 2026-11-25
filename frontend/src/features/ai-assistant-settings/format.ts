/** A price in dollars per million tokens, or the placeholder when OpenRouter gives none. */
export function formatPrice(price: string | null, placeholder: string): string {
  return price === null ? placeholder : `$${price}`
}
