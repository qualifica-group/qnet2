import { useCallback, useMemo, useState } from 'react'
import { useFieldArray, useWatch, type UseFormReturn } from 'react-hook-form'
import {
  lineFromAvailable,
  newLine,
  type NewLineFields,
  totalsOf,
  type VatRefs,
} from '@/features/invoices/invoice-editor-lines'
import type { InvoiceFormValues } from '@/features/invoices/invoice-schema'
import type { AvailableInvoiceLine, VatRateRef } from '@/features/invoices/types'

const NO_AVAILABLE_LINES: AvailableInvoiceLine[] = []

/**
 * The document lines of an invoice form (issue draft and the detail's Righe
 * row): the field array, the VAT percentages the live totals need and the
 * add actions (available work-order lines, catalog or free rows).
 */
export function useInvoiceLines(
  form: UseFormReturn<InvoiceFormValues>,
  initialVatRefs: VatRefs,
  availableLines: AvailableInvoiceLine[] = NO_AVAILABLE_LINES,
) {
  const { control, getValues } = form
  const lineArray = useFieldArray({ control, name: 'lines' })
  const { append } = lineArray

  // UI cache of the percentage per VAT id: the pickers expose ids, the totals need rates.
  const [vatRefs, setVatRefs] = useState<VatRefs>(initialVatRefs)
  const rememberVat = useCallback((ref: VatRateRef | null) => {
    if (ref === null) return
    setVatRefs((current) => (current[ref.id] ? current : { ...current, [ref.id]: ref }))
  }, [])

  const lines = useWatch({ control, name: 'lines' })
  const totals = useMemo(() => totalsOf(lines, vatRefs), [lines, vatRefs])

  const addedQuoteLineIds = useMemo(
    () => new Set(lines.flatMap((line) => (line.quote_line_id === null ? [] : [line.quote_line_id]))),
    [lines],
  )

  const addAvailableLine = useCallback(
    (quoteLineId: number) => {
      const line = availableLines.find((candidate) => candidate.quote_line_id === quoteLineId)
      if (line) {
        rememberVat(line.vat_rate)
        append(lineFromAvailable(line))
      }
    },
    [append, rememberVat, availableLines],
  )

  const addAllAvailableLines = useCallback(() => {
    const added = new Set(getValues('lines').map((line) => line.quote_line_id))
    const missing = availableLines.filter((line) => !added.has(line.quote_line_id))
    missing.forEach((line) => rememberVat(line.vat_rate))
    append(missing.map(lineFromAvailable))
  }, [append, getValues, rememberVat, availableLines])

  /** Appends a catalog or free row; a free row inherits the VAT of the last row when there is one. */
  const addLine = useCallback(
    (fields: NewLineFields) => {
      const inheritedVat = getValues('lines').at(-1)?.vat_rate_id
      append(newLine(inheritedVat === undefined ? fields : { vat_rate_id: inheritedVat, ...fields }))
    },
    [append, getValues],
  )

  return { lineArray, lines, vatRefs, rememberVat, totals, addedQuoteLineIds, addAvailableLine, addAllAvailableLines, addLine }
}

export type InvoiceLinesState = ReturnType<typeof useInvoiceLines>
