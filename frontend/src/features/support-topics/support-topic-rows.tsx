import { useTranslation } from 'react-i18next'
import {
  IconInlineRow,
  NumberInlineRow,
  SwitchInlineRow,
  TextInlineRow,
} from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { SupportTopicRelationRows } from '@/features/support-topics/support-topic-relation-rows'
import type { SupportTopicFormValues } from '@/features/support-topics/support-topic-schema'
import type { SupportTopicDetail } from '@/features/support-topics/types'

interface SupportTopicRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** The saved topic on a detail; absent on the create draft. */
  supportTopic?: SupportTopicDetail
}

type Values = SupportTopicFormValues

/** The topic's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function SupportTopicRows({ inline, canEdit, supportTopic }: SupportTopicRowsProps) {
  const { t } = useTranslation()
  const base = { inline, canEdit }

  return (
    <>
      <TextInlineRow<Values> {...base} name="name" label={t('supportTopics.form.name')} />
      <TextInlineRow<Values> {...base} name="description" label={t('supportTopics.form.description')} hint={t('supportTopics.form.descriptionHint')} multiline />
      <IconInlineRow<Values> {...base} name="icon" label={t('supportTopics.form.icon')} />
      <NumberInlineRow<Values> {...base} name="sort_order" label={t('supportTopics.form.sortOrder')} step={1} />
      <SwitchInlineRow<Values> {...base} name="is_active" label={t('supportTopics.form.isActive')} />
      <SupportTopicRelationRows {...base} supportTopic={supportTopic} />
      <NumberInlineRow<Values> {...base} name="due_days" label={t('supportTopics.form.dueDays')} hint={t('supportTopics.form.dueDaysHint')} min={0} max={365} step={1} suffix={t('supportTopics.form.daysSuffix')} />
      <NumberInlineRow<Values> {...base} name="urgent_due_days" label={t('supportTopics.form.urgentDueDays')} hint={t('supportTopics.form.urgentDueDaysHint')} min={0} max={365} step={1} suffix={t('supportTopics.form.daysSuffix')} />
      <SwitchInlineRow<Values> {...base} name="is_private" label={t('supportTopics.form.isPrivate')} />
      <SwitchInlineRow<Values> {...base} name="requires_closure_feedback" label={t('supportTopics.form.requiresClosureFeedback')} />
    </>
  )
}
