import { useCallback, useMemo, useState } from 'react'
import { useForm, type Path, type UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { z } from 'zod'
import type { IRowNode } from 'ag-grid-community'
import type { ApiErrorResponse } from '@/api/types'
import {
  linesToFormValues,
  originalLineInputs,
  sameLines,
  toLineInputs,
  vatRatePercentsFromLines,
} from '@/features/quotes/quote-line-values'
import { MAX_LINES_PER_TAB, quoteLineRowSchema } from '@/features/quotes/quote-schema'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { ProductLineRow } from '@/features/product-lines/types'
import { toProductLineRows } from '@/features/request-management/request-work-payload'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'
import { updateTableCell } from '@/features/table/api'
import type { TableRow } from '@/features/table/types'

/**
 * The quick-edit form values: the offer rows, plus the classification they are
 * scoped by. `product_lines` is carried READ-ONLY — `RequestOfferLinesField`
 * watches it to scope the product picker and to resolve the `single`-mode row
 * cap — and never travels: this cell edits the offer rows alone, the
 * classification has its own inline-editable column on the same grid.
 */
export interface OfferLinesFormValues {
  product_lines: ProductLineRow[]
  offer_lines: QuoteLineFormValues[]
}

/** The edited column, which is also its `editableField` and this form's own RHF path. */
const OFFER_LINES_FIELD = 'offer_lines'

/** The key a cell PATCH's 422 reports under: the engine validates one `value`, whatever its shape. */
const CELL_VALUE_KEY = 'value'

function buildDefaultValues(panel: RequestWorkPanelWithPermissions): OfferLinesFormValues {
  return {
    product_lines: toProductLineRows(panel.product_lines),
    // The SAME hydration the work panel uses, minus the provvigioni block the
    // endpoint prohibits on this channel.
    offer_lines: linesToFormValues(panel.offer_lines, false),
  }
}

/**
 * Maps the cell endpoint's 422 onto the form: the engine reports on `value`
 * (and `value.<i>.<field>` for a per-row rule), while the row editor binds
 * `offer_lines.<i>.<field>` — the same paths, under the generic engine's own
 * name. Without this rename a per-row message would land nowhere and the
 * operator would only see a summary.
 */
function applyCellValidationErrors(
  error: unknown,
  setError: UseFormSetError<OfferLinesFormValues>,
): boolean {
  if (!axios.isAxiosError<ApiErrorResponse>(error) || error.response?.status !== 422) {
    return false
  }

  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  let applied = false

  for (const [key, messages] of Object.entries(errors ?? {})) {
    const message = messages[0]
    if (message === undefined || (key !== CELL_VALUE_KEY && !key.startsWith(`${CELL_VALUE_KEY}.`))) {
      continue
    }
    const path = `${OFFER_LINES_FIELD}${key.slice(CELL_VALUE_KEY.length)}` as Path<OfferLinesFormValues>
    setError(path, { type: 'server', message })
    applied = true
  }

  return applied
}

interface UseOfferLinesFormOptions {
  /** The grid row this cell belongs to: replaced wholesale on success, like every other cell edit. */
  node: IRowNode<TableRow>
  /** Called once the dialog has nothing left to do — a commit that landed, or a submit with no change. */
  onDone: () => void
}

/**
 * RHF/Zod wiring of the "Linee di prodotto" cell editor (user directive
 * 2026-09-07): the SAME per-row schema and the SAME hydration/wire mappers as
 * the work panel, committed through the SAME
 * `PATCH /tables/{domain}/rows/{row}` every other inline-edited cell goes
 * through — so the row is replaced by the server's re-mapped copy exactly as
 * `useTableCellEdit` would, and the server-side rules are the engine's own
 * (`CellValueValidator` -> `updateWork()`), not a second channel's.
 *
 * Sparse in the same sense as every other cell: an untouched collection never
 * travels, and the dialog simply closes.
 */
export function useOfferLinesForm(
  panel: RequestWorkPanelWithPermissions,
  { node, onDone }: UseOfferLinesFormOptions,
) {
  const { t } = useTranslation()
  const [submitError, setSubmitError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z.object({
        // Watched only (see `OfferLinesFormValues`): no rule of its own here,
        // its collection-level rules belong to the surfaces that write it.
        product_lines: z.array(z.custom<ProductLineRow>()),
        offer_lines: z.array(quoteLineRowSchema(t)).max(MAX_LINES_PER_TAB, t('quotes.form.linesMax')),
      }),
    [t],
  )

  const defaultValues = useMemo(() => buildDefaultValues(panel), [panel])

  const form = useForm<OfferLinesFormValues>({ resolver: zodResolver(schema), defaultValues })

  // The VAT-percent cache the rows' live preview reads (AC-071), seeded from
  // the persisted lines' own hydrated rate: the `vat-rates/for-select` picker
  // never exposes a percentage.
  const [vatRatePercentById, setVatRatePercentById] = useState<Record<number, number>>(() =>
    vatRatePercentsFromLines(panel.offer_lines),
  )
  const rememberVatRatePercent = useCallback((vatRateId: number, percent: number) => {
    setVatRatePercentById((previous) =>
      previous[vatRateId] === percent ? previous : { ...previous, [vatRateId]: percent },
    )
  }, [])
  const vatRatePercentFor = useCallback(
    (vatRateId: number) => vatRatePercentById[vatRateId] ?? null,
    [vatRatePercentById],
  )

  const onSubmit = form.handleSubmit(
    async (values) => {
      setSubmitError(null)

      // Step 1: the sparse diff, on the WIRE shape the endpoint reads.
      const lines = toLineInputs(values.offer_lines)
      if (sameLines(lines, originalLineInputs(panel.offer_lines, false))) {
        onDone()
        return
      }

      // Step 2: the generic cell PATCH. `commissions` never travel here (the
      // rows carry none, the server prohibits them and preserves what the
      // Offerte form configured), so the value is the plain numeric row the
      // payload type describes.
      try {
        const row = await updateTableCell(REQUEST_MANAGEMENT_DOMAIN, panel.id, {
          column: OFFER_LINES_FIELD,
          value: lines.map((line) => ({
            ...(line.id !== undefined ? { id: line.id } : {}),
            product_id: line.product_id,
            quantity: line.quantity,
            unit_price: line.unit_price,
            vat_rate_id: line.vat_rate_id ?? null,
            sort_order: line.sort_order ?? null,
          })),
        })

        // Step 3: the row the server re-mapped, in place — the same
        // `node.setData` swap `useTableCellEdit` performs for every other cell.
        node.setData(row)
        onDone()
      } catch (error) {
        if (!applyCellValidationErrors(error, form.setError)) {
          setSubmitError(resolveErrorMessage(error, t('table.cellUpdateError')))
        }
      }
    },
    () => {
      setSubmitError(t('requestManagement.offerLines.validationSummary'))
    },
  )

  return {
    form,
    onSubmit,
    submitError,
    isSubmitting: form.formState.isSubmitting,
    vatRatePercentFor,
    rememberVatRatePercent,
  }
}

/**
 * The server's own message when it sent one (the engine's D-9 contract), the
 * generic fallback otherwise — the same resolution the grid's toast applies,
 * shown inside the dialog instead: the operator is looking at the rows that
 * were refused, not at the grid behind them.
 */
function resolveErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return fallback
}
