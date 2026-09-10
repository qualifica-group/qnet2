/**
 * The row grid, shared by the header strip and every row. `withCommissions`
 * drops the revenue tab's provvigioni column for a channel that does not own
 * that block (Gestione Richieste, user directive 2026-08-07) — the default
 * keeps the Offerte form's own layout unchanged. `simplified` (spec 0114)
 * drops the three columns a simplified card never lets the operator edit —
 * quantity, unit price, VAT rate — leaving the product picker, code, unit of
 * measure and the three read-only amounts (D-2). Never reaches the Offerte
 * module (D-3): its default is `false`, like `withCommissions`'s default is
 * `true`.
 */
export function quoteLineGridClass(
  variant: 'revenue' | 'cost',
  withCommissions = true,
  simplified = false,
): string {
  if (simplified) {
    return variant === 'revenue' && withCommissions
      ? 'grid grid-cols-[minmax(200px,1.4fr)_88px_64px_90px_90px_100px_36px_36px] gap-2'
      : 'grid grid-cols-[minmax(200px,1.4fr)_88px_64px_90px_90px_100px_36px] gap-2'
  }
  return variant === 'revenue' && withCommissions
    ? 'grid grid-cols-[minmax(200px,1.4fr)_88px_112px_64px_128px_140px_90px_90px_100px_36px_36px] gap-2'
    : 'grid grid-cols-[minmax(200px,1.4fr)_88px_112px_64px_128px_140px_90px_90px_100px_36px] gap-2'
}

export function quoteLineMinWidthClass(
  variant: 'revenue' | 'cost',
  withCommissions = true,
  simplified = false,
): string {
  if (simplified) {
    return variant === 'revenue' && withCommissions ? 'min-w-[664px]' : 'min-w-[614px]'
  }
  return variant === 'revenue' && withCommissions ? 'min-w-[1044px]' : 'min-w-[994px]'
}
