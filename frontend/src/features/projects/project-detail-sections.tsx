import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { cn } from '@/lib/utils'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { formatDecimal } from '@/features/products/column-renderers'
import { persistedProjectValues } from '@/features/projects/project-record'
import {
  ProjectClassificationRecordSection,
  ProjectGeographyRecordSection,
  ProjectIdentityRecordSection,
  ProjectPlanningRecordSection,
} from '@/features/projects/project-record-sections'
import type { ProjectDetailEditor } from '@/features/projects/use-project-inline-edit'
import type { ProjectDetailWithPermissions as ProjectDetailData } from '@/features/projects/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/**
 * Budget over-allocation warning (BR-7, AC-044): shown only when
 * `remaining_budget` parses to a negative number. `remaining_budget` is `null`
 * when the project has no `total_budget` set (A-1) — no warning in that case,
 * since there is no residual to check.
 *
 * First row of the grid, full width: it is an alert, so it must be read before
 * the fields it is about, not found among them.
 */
function BudgetOverallocationWarning({ remainingBudget }: { remainingBudget: string | null }) {
  const { t } = useTranslation()
  const remaining = remainingBudget === null ? null : Number(remainingBudget)

  if (remaining === null || remaining >= 0) {
    return null
  }

  return (
    <div
      role="alert"
      className={cn(
        'flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive',
        FULL_WIDTH_SECTION_CLASS,
      )}
    >
      <AlertTriangle className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
      <span>
        {t('projects.detail.overallocatedWarning', { amount: formatDecimal(Math.abs(remaining)) })}
      </span>
    </div>
  )
}

interface ProjectDetailSectionsProps {
  project: ProjectDetailData
  editor: ProjectDetailEditor
}

/**
 * The project record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0220): the over-allocation alert first (it must be
 * read before the fields it is about), then identity, classification,
 * geography, planning and the custom fields ("campi flessibili"). The create
 * form renders the same sections in the same order (`ProjectCreateSections`).
 */
export function ProjectDetailSections({ project, editor }: ProjectDetailSectionsProps) {
  const { form, inline } = editor
  const sectionProps = { values: persistedProjectValues(project), form, inline }

  return (
    <RecordSectionsGrid>
      <BudgetOverallocationWarning remainingBudget={project.remaining_budget} />
      <ProjectIdentityRecordSection {...sectionProps} />
      <ProjectClassificationRecordSection {...sectionProps} />
      <ProjectGeographyRecordSection {...sectionProps} />
      <ProjectPlanningRecordSection {...sectionProps} />
      <CustomFieldRecordSections
        resource="projects"
        values={sectionProps.values.custom_fields}
        inline={inline}
        control={form.control}
      />
    </RecordSectionsGrid>
  )
}
