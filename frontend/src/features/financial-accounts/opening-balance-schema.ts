import { z } from 'zod'

const MAX_ABS_CENTS = 99_999_999_999
const AMOUNT_RE = /^-?\d+(\.\d{1,2})?$/

/** Accepts the Italian decimal comma; thousands separators are not supported. */
function normalizeAmount(raw: string): string {
  return raw.trim().replace(',', '.')
}

/** Mirrors the server rules of PATCH opening-balance (spec 0360): 2 decimals, +-999999999.99, date required. */
export const openingBalanceSchema = z.object({
  opening_balance: z
    .string()
    .transform(normalizeAmount)
    .pipe(
      z
        .string()
        .regex(AMOUNT_RE, 'amountRequired')
        .refine((value) => Math.abs(Math.round(Number(value) * 100)) <= MAX_ABS_CENTS, 'amountRange'),
    ),
  opening_balance_date: z.string().min(1, 'dateRequired'),
})

export type OpeningBalanceFormInput = z.input<typeof openingBalanceSchema>
export type OpeningBalanceFormValues = z.output<typeof openingBalanceSchema>
