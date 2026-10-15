import { IdCard } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { AgentFiscalProfileSections } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-rows'
import { useAgentFiscalProfileDraftRefs } from '@/features/agent-fiscal-profiles/use-agent-fiscal-profile-draft-refs'
import { useAgentFiscalProfileForm } from '@/features/agent-fiscal-profiles/use-agent-fiscal-profile-form'
import type { AgentFiscalProfileDetail } from '@/features/agent-fiscal-profiles/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const AGENT_FISCAL_PROFILE_FORM_ID = 'agent-fiscal-profile-form'

const CREATE_MODE = { type: 'create' } as const

interface AgentFiscalProfileFormBodyProps {
  onSuccess: (profile: AgentFiscalProfileDetail) => void
  onCancel: () => void
}

/**
 * The create form as a replica of the detail (spec 0264 D-4): the same rows,
 * closed until clicked (Fatto/Ripristina, nothing saved per row); Salva
 * validates and creates everything at once.
 */
export function AgentFiscalProfileFormBody({ onSuccess, onCancel }: AgentFiscalProfileFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useAgentFiscalProfileForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const refs = useAgentFiscalProfileDraftRefs(form)
  const heading = refs.referent?.name ?? t('agentFiscalProfiles.form.newProfile')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={AGENT_FISCAL_PROFILE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('agentFiscalProfiles.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<IdCard />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('agentFiscalProfiles.form.save'),
          saving: t('agentFiscalProfiles.form.saving'),
          cancel: t('common.cancel'),
        }}
      >
        <AgentFiscalProfileSections inline={draft} canEdit refs={refs} />
      </RecordCreateLayout>
    </Form>
  )
}
