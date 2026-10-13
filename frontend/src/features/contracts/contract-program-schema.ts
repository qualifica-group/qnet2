import { z } from 'zod'
import type { TFunction } from 'i18next'
import { blankToNull } from '@/lib/utils'
import type { ContractProgramGroupPayload } from '@/features/contracts/types'
import { todayIsoDate } from '@/features/work-order-costs/work-order-costs-schema'

/** Mirrors `work_orders.title`'s `string(191)` column limit (`workOrders.form.titleMax`). */
const TITLE_MAX_LENGTH = 191

/**
 * "Programma" (spec 0215): one dialog session builds N work-order groups.
 * `common` holds the shared values a new group inherits (never validated on
 * its own: only the groups travel); every group carries what a commessa
 * cannot be left without — `type`, `start_date`, at least one Responsabile
 * and at least one offer line — while `title` is optional (blank = the
 * automatic `<code> - <products>` title, D-2) and `key` is client-only.
 * Messages reuse the work-order form's own strings (`workOrders.form.*`).
 */
export function buildContractProgramSchema(t: TFunction) {
  const type = z.enum(['processing', 'project'], { message: t('workOrders.form.typeRequired') })

  return z.object({
    common: z.object({
      type,
      start_date: z.string(),
      supervisor_ids: z.array(z.number()),
      task_template_id: z.number().nullable(),
    }),
    groups: z
      .array(
        z.object({
          key: z.string(),
          title: z.string().max(TITLE_MAX_LENGTH, t('workOrders.form.titleMax')),
          type,
          start_date: z.string().min(1, t('workOrders.form.startDateRequired')),
          supervisor_ids: z.array(z.number()).min(1, t('workOrders.form.supervisorsRequired')),
          task_template_id: z.number().nullable(),
          quote_line_ids: z.array(z.number()).min(1, t('contracts.actions.programDialog.linesRequired')),
        }),
      )
      .min(1, t('contracts.actions.programDialog.groupsRequired')),
  })
}

export type ContractProgramFormValues = z.infer<ReturnType<typeof buildContractProgramSchema>>
export type ContractProgramCommon = ContractProgramFormValues['common']
export type ContractProgramGroup = ContractProgramFormValues['groups'][number]

/** "Data inizio" starts at today (local calendar), still editable; new groups inherit it. */
export function contractProgramDefaultValues(): ContractProgramFormValues {
  return {
    common: { type: 'processing', start_date: todayIsoDate(), supervisor_ids: [], task_template_id: null },
    groups: [],
  }
}

/** Body of `POST /contracts/{id}/work-orders/batch`: groups in order, blank title -> `null`, no client `key`. */
export function toBatchPayload(values: ContractProgramFormValues): { groups: ContractProgramGroupPayload[] } {
  return {
    groups: values.groups.map((group) => ({
      title: blankToNull(group.title),
      type: group.type,
      start_date: group.start_date,
      supervisor_ids: group.supervisor_ids,
      task_template_id: group.task_template_id,
      quote_line_ids: group.quote_line_ids,
    })),
  }
}
