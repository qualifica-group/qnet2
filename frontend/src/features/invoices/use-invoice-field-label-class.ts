import { useContext } from 'react'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'

/**
 * Inside a record row that already shows this field's label, the control's own
 * label stays for assistive tech only (the `MetaField` rule, spec 0195).
 */
export function useInvoiceFieldLabelClass(name: string): string {
  return useContext(MetaFieldRowContext) === name ? 'sr-only' : 'text-xs'
}
