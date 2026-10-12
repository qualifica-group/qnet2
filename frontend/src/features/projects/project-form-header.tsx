import { useTranslation } from 'react-i18next'
import { FolderKanban, Loader2, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { ProjectStatsStrip } from '@/features/projects/project-detail-header'
import type { ProjectRecordValues } from '@/features/projects/project-record'

interface ProjectFormHeaderProps {
  /** The draft as the rows read it: name and code identify the project being created. */
  values: ProjectRecordValues
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the project create form, the detail's own
 * (`ProjectDetailHeader` + `ProjectStatsStrip`, spec 0220): monogram and title
 * follow the name being typed, the subtitle the code, the strip the budget —
 * nothing is allocated to a project that does not exist yet. A refused submit
 * is reported right under the actions.
 */
export function ProjectFormHeader({ values, formId, isSubmitting, submitError, onCancel }: ProjectFormHeaderProps) {
  const { t } = useTranslation()
  const heading = values.name.trim() || t('projects.form.createTitle')
  const totalBudget = values.total_budget

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<FolderKanban />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={values.code || t('projects.form.createSubtitle')}
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('projects.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('projects.form.saving') : t('projects.form.save')}
            </Button>
          </>
        }
      />

      {submitError ? (
        <div
          role="alert"
          className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      ) : null}

      <ProjectStatsStrip totalBudget={totalBudget} allocatedBudget={0} remainingBudget={totalBudget} campaignsCount={0} />
    </>
  )
}
