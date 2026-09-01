/**
 * The row grid, shared by the header strip and every row. `withCommissions`
 * drops the revenue tab's provvigioni column for a channel that does not own
 * that block (Gestione Richieste, user directive 2026-08-07) — the default
 * keeps the Offerte form's own layout unchanged.
 */
export function quoteLineGridClass(variant: 'revenue' | 'cost', withCommissions = true): string {
  return variant === 'revenue' && withCommissions
    ? 'grid grid-cols-[minmax(200px,1.4fr)_88px_112px_64px_128px_140px_90px_90px_100px_36px_36px] gap-2'
    : 'grid grid-cols-[minmax(200px,1.4fr)_88px_112px_64px_128px_140px_90px_90px_100px_36px] gap-2'
}

export function quoteLineMinWidthClass(variant: 'revenue' | 'cost', withCommissions = true): string {
  return variant === 'revenue' && withCommissions ? 'min-w-[1044px]' : 'min-w-[994px]'
}
