import { useTranslation } from 'react-i18next'
import { AlertTriangle, CalendarRange, FileText, Globe, Tags } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { cn } from '@/lib/utils'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { formatDecimal } from '@/features/products/column-renderers'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import { formatDate } from '@/lib/formatting/date-display'
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
}

/**
 * The record's `RecordSectionsGrid` body: the over-allocation alert first, then
 * the description, the records the project points at, and its own fields. The
 * budget numbers themselves live in the KPI strip above — repeating them here
 * would be the same figures twice on one card.
 */
export function ProjectDetailSections({ project }: ProjectDetailSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      <BudgetOverallocationWarning remainingBudget={project.remaining_budget} />

      {project.description ? (
        <RecordSection
          title={t('projects.form.description')}
          icon={<FileText />}
          className={FULL_WIDTH_SECTION_CLASS}
        >
          {/* `whitespace-pre-wrap`: a pasted multi-line brief keeps its shape. */}
          <p className="text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground">
            {project.description}
          </p>
        </RecordSection>
      ) : null}

      {/* Named after the module's OWN `form.sections.*` like every other detail
          in the app: the Partner and the Sede live in the section that already
          owned them, simply rendered as links, rather than pulled out into a
          separate "links" group (user directive 2026-09-11). The pipeline
          status is NOT repeated here — it is the badge in the identity band. */}
      <RecordSection title={t('projects.form.sections.classification.title')} icon={<Tags />}>
        <RecordFieldList>
          {/*
            The Partner is a `Referent`, NOT a User — so it gets a `RecordLink`
            to its own module, never `UserProfileHoverCard` (which opens the
            shared USER detail Sheet and would resolve the wrong record).
          */}
          <RecordField label={t('projects.form.partner')}>
            {project.partner ? (
              <RecordLink domain="referents" id={project.partner.id}>
                {project.partner.name}
              </RecordLink>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>

          <RecordField label={t('projects.form.operationalSite')}>
            {project.operational_site && project.operational_site.label ? (
              <RecordLink domain="operational-sites" id={project.operational_site.id}>
                {project.operational_site.label}
              </RecordLink>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>

          <RecordField label={t('projects.form.productLines')}>
            <ProductLinesReadOnlyList lines={project.product_lines} />
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('projects.form.sections.geography.title')} icon={<Globe />}>
        <RecordFieldList>
          <RecordField label={t('geo.country')}>
            {project.country?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('geo.state')}>{project.state?.name ?? <DetailEmpty />}</RecordField>
          <RecordField label={t('geo.province')}>
            {project.province?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('geo.city')}>{project.city?.name ?? <DetailEmpty />}</RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection
        title={t('projects.form.sections.planning.title')}
        icon={<CalendarRange />}
        className={FULL_WIDTH_SECTION_CLASS}
      >
        <RecordFieldList>
          <RecordField label={t('projects.form.startDate')}>
            {formatDate(project.start_date) || <DetailEmpty />}
          </RecordField>
          <RecordField label={t('projects.form.endDate')}>
            {formatDate(project.end_date) || <DetailEmpty />}
          </RecordField>
          <RecordField label={t('projects.form.targetLead')}>
            {project.target_lead ?? <DetailEmpty />}
          </RecordField>
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}
