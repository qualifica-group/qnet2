import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Sparkles } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { SkillRows } from '@/features/skills/skill-rows'
import { useSkillDraftJobRoles } from '@/features/skills/use-skill-draft-job-roles'
import { useSkillForm } from '@/features/skills/use-skill-form'
import type { Skill } from '@/features/skills/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const FORM_ID = 'skill-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface SkillFormBodyProps {
  onSuccess: (skill: Skill) => void
  onCancel: () => void
}

/**
 * The skill create form as a replica of the detail: the same rows, closed until clicked
 * (nothing saved per row); Save validates and creates everything at once.
 */
export function SkillFormBody({ onSuccess, onCancel }: SkillFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useSkillForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const jobRoles = useSkillDraftJobRoles(form.control)
  const heading = name.trim() || t('skills.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('skills.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Sparkles />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('skills.form.save'),
          saving: t('skills.form.saving'),
          cancel: t('skills.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('skills.form.sections.identity.title')} full>
            <RecordFieldList>
              <SkillRows inline={draft} canEdit jobRoles={jobRoles} />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
