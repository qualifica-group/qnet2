export function quoteLineGridClass(variant: 'revenue' | 'cost'): string {
  return variant === 'revenue'
    ? 'grid grid-cols-[minmax(200px,1.4fr)_88px_112px_128px_140px_90px_90px_100px_36px_36px] gap-2'
    : 'grid grid-cols-[minmax(200px,1.4fr)_88px_112px_128px_140px_90px_90px_100px_36px] gap-2'
}

export function quoteLineMinWidthClass(variant: 'revenue' | 'cost'): string {
  return variant === 'revenue' ? 'min-w-[980px]' : 'min-w-[930px]'
}
