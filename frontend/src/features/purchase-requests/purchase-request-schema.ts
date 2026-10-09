import { z } from 'zod'
import type { TFunction } from 'i18next'
import { LINE_STATUSES, PRIORITIES } from '@/features/purchase-requests/types'

/**
 * Zod schema of the RDA form, mirroring the frozen server rules of spec 0208
 * (the client never replaces server validation). Factory takes `t` so the
 * messages are localized (`purchaseRequests.errors.*`).
 */

export const LINES_MIN = 1
export const LINES_MAX = 200
const SUBJECT_MAX = 255
const DESCRIPTION_MAX = 500
const FOOTER_MAX = 5000
const QUANTITY_MAX = 999_999_999
const UNIT_PRICE_LIMIT = 99_999_999_999

/** Required relation id: the form holds `null` until the user picks one. */
function requiredId(message: string) {
  return z
    .number()
    .nullable()
    .refine((value): boolean => value !== null, message)
}

function buildLineSchema(t: TFunction) {
  return z.object({
    /** Present on a persisted line, absent on a new one. */
    id: z.number().int().optional(),
    /** Read-only context of the persisted line; never sent. */
    status: z.enum(LINE_STATUSES),
    locked: z.boolean(),
    can_delete: z.boolean(),
    /** Statuses the server lets the actor move this line to (`abilities.transitions`). */
    transitions: z.array(z.enum(LINE_STATUSES)),
    vat_rate_percent: z.number().nullable(),
    pending_files: z.array(z.custom<File>()),
    product_id: z.number().int().nullable(),
    description: z
      .string()
      .trim()
      .min(1, t('purchaseRequests.errors.descriptionRequired'))
      .max(DESCRIPTION_MAX, t('purchaseRequests.errors.descriptionMax')),
    reason: z.string().max(FOOTER_MAX, t('purchaseRequests.errors.textMax')),
    unit_of_measure_id: z.number().int().nullable(),
    quantity: z
      .number(t('purchaseRequests.errors.quantityInvalid'))
      .gt(0, t('purchaseRequests.errors.quantityPositive'))
      .max(QUANTITY_MAX, t('purchaseRequests.errors.quantityMax')),
    unit_price: z
      .number(t('purchaseRequests.errors.unitPriceInvalid'))
      .min(0, t('purchaseRequests.errors.unitPriceNegative'))
      .max(UNIT_PRICE_LIMIT, t('purchaseRequests.errors.unitPriceMax')),
    vat_rate_id: z.number().int().nullable(),
  })
}

export function buildPurchaseRequestSchema(t: TFunction) {
  const footer = z.string().max(FOOTER_MAX, t('purchaseRequests.errors.textMax'))
  return z.object({
    subject: z
      .string()
      .trim()
      .min(1, t('purchaseRequests.errors.subjectRequired'))
      .max(SUBJECT_MAX, t('purchaseRequests.errors.subjectMax')),
    requested_at: z.string().min(1, t('purchaseRequests.errors.requestedAtRequired')),
    priority: z.enum(PRIORITIES),
    requester_id: requiredId(t('purchaseRequests.errors.requesterRequired')),
    function_manager_id: requiredId(t('purchaseRequests.errors.functionManagerRequired')),
    customer_id: z.number().int().nullable(),
    supplier_id: z.number().int().nullable(),
    work_order_id: z.number().int().nullable(),
    company_id: requiredId(t('purchaseRequests.errors.companyRequired')),
    company_site_id: requiredId(t('purchaseRequests.errors.companySiteRequired')),
    operational_site_id: requiredId(t('purchaseRequests.errors.operationalSiteRequired')),
    business_function_id: requiredId(t('purchaseRequests.errors.businessFunctionRequired')),
    notes: footer,
    delivery_terms: footer,
    procurement_plan: footer,
    technical_requirements: footer,
    special_conditions: footer,
    pending_files: z.array(z.custom<File>()),
    lines: z
      .array(buildLineSchema(t))
      .min(LINES_MIN, t('purchaseRequests.errors.linesRequired'))
      .max(LINES_MAX, t('purchaseRequests.errors.linesMax')),
  })
}

export type PurchaseRequestFormValues = z.infer<ReturnType<typeof buildPurchaseRequestSchema>>
export type PurchaseRequestLineFormValues = PurchaseRequestFormValues['lines'][number]
