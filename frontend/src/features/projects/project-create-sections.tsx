import type { UseFormReturn } from 'react-hook-form'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { ProjectRecordValues } from '@/features/projects/project-record'
import {
  ProjectClassificationRecordSection,
  ProjectGeographyRecordSection,
  ProjectIdentityRecordSection,
  ProjectPlanningRecordSection,
} from '@/features/projects/project-record-sections'
import type { ProjectFormValues } from '@/features/projects/use-project-form'

interface ProjectCreateSectionsProps {
  /** The draft as the closed rows read it (`useProjectDraftValues`, shared with the header). */
  values: ProjectRecordValues
  form: UseFormReturn<ProjectFormValues>
  draft: InlineEdit
}

/**
 * The create form as a replica of the project detail (spec 0220 D-2): the
 * detail's sections, rows, labels and order, every row CLOSED until clicked
 * and opening on the same component the detail edits in place. Nothing is
 * saved per row: the header's Salva validates and creates the whole draft.
 */
export function ProjectCreateSections({ values, form, draft }: ProjectCreateSectionsProps) {
  const sectionProps = { values, form, inline: draft }

  return (
    <RecordSectionsGrid>
      <ProjectIdentityRecordSection {...sectionProps} />
      <ProjectClassificationRecordSection {...sectionProps} />
      <ProjectGeographyRecordSection {...sectionProps} />
      <ProjectPlanningRecordSection {...sectionProps} />
      <CustomFieldRecordSections resource="projects" values={values.custom_fields} inline={draft} control={form.control} />
    </RecordSectionsGrid>
  )
}
