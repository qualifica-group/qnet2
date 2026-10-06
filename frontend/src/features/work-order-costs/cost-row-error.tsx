import type { FieldError } from 'react-hook-form'

/** Row fields that carry a validation error, local (Zod) or server-side (422). */
export type CostRowErrors = Partial<
  Record<
    | 'product_id'
    | 'quantity'
    | 'unit_price'
    | 'vat_rate_id'
    | 'quote_line_id'
    | 'incurred_on'
    | 'supplier_id'
    | 'document_reference'
    | 'additional_description',
    FieldError
  >
>

export function CostRowError({ id, error }: { id: string; error?: FieldError }) {
  return error ? (
    <span id={id} role="alert" className="text-[11px] text-destructive">
      {error.message}
    </span>
  ) : null
}
