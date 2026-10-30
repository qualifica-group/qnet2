import { z } from 'zod'
import type { TFunction } from 'i18next'
import { isKnownIconName } from '@/features/custom-fields/icon-catalog'

/**
 * Zod schema of the support topic form (spec 0334 `data_contract`), a factory
 * so messages are localized. Mirrors the server rules: `assignee_ids` min 1,
 * `watcher_ids` disjoint from `assignee_ids`, `due_days` 0..365. `icon` uses
 * the empty string for "unset" (mapped to `null` by the payload builder).
 */

const NAME_MAX_LENGTH = 191
const DESCRIPTION_MAX_LENGTH = 500
const ICON_MAX_LENGTH = 64
const DAYS_MAX = 365

export function buildSupportTopicSchema(t: TFunction) {
  const days = z
    .number(t('supportTopics.form.dueDaysInvalid'))
    .int(t('supportTopics.form.dueDaysInvalid'))
    .min(0, t('supportTopics.form.dueDaysInvalid'))
    .max(DAYS_MAX, t('supportTopics.form.dueDaysInvalid'))

  return z
    .object({
      name: z
        .string()
        .min(1, t('supportTopics.form.nameRequired'))
        .max(NAME_MAX_LENGTH, t('supportTopics.form.nameMax')),
      description: z.string().max(DESCRIPTION_MAX_LENGTH, t('supportTopics.form.descriptionMax')).nullable(),
      icon: z
        .string()
        .max(ICON_MAX_LENGTH)
        .refine((name) => name === '' || isKnownIconName(name), t('supportTopics.form.iconInvalid')),
      sort_order: z.number(t('supportTopics.form.sortOrderInvalid')).int(t('supportTopics.form.sortOrderInvalid')),
      is_active: z.boolean(),
      task_type_id: z.number().nullable(),
      task_category_id: z.number().nullable(),
      task_priority_id: z.number().nullable(),
      urgent_task_priority_id: z.number().nullable(),
      task_importance_id: z.number().nullable(),
      due_days: days,
      urgent_due_days: days.nullable(),
      is_private: z.boolean(),
      requires_closure_feedback: z.boolean(),
      assignee_ids: z.array(z.number()).min(1, t('supportTopics.form.assigneesRequired')),
      watcher_ids: z.array(z.number()),
    })
    .superRefine((values, ctx) => {
      const assignees = new Set(values.assignee_ids)
      if (values.watcher_ids.some((id) => assignees.has(id))) {
        ctx.addIssue({
          code: 'custom',
          path: ['watcher_ids'],
          message: t('supportTopics.form.watchersOverlap'),
        })
      }
    })
}

export type SupportTopicFormValues = z.infer<ReturnType<typeof buildSupportTopicSchema>>
