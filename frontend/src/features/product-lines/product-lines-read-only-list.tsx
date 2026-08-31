import { DetailEmpty } from '@/components/detail/detail-panel'
import type { ProductLine } from '@/features/product-lines/types'

/**
 * Read-only list of confirmed funzione-aziendale + categoria-prodotto rows
 * (spec 0040 amendment rev.3, AC-101) — the SINGLE rendering shared by the
 * Opportunity record and the Offerta record, which shows its parent's rows as
 * context (user directive 2026-08-31: "cerchiamo di rendere tutto simile").
 * Falls back to the kit's empty placeholder when there is no row.
 */
export function ProductLinesReadOnlyList({ lines }: { lines: ProductLine[] }) {
  if (lines.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-col gap-1">
      {lines.map((line) => (
        <li key={line.id}>
          <span className="font-medium">{line.business_function.name}</span>
          <span className="text-muted-foreground"> — {line.product_category.name}</span>
        </li>
      ))}
    </ul>
  )
}
