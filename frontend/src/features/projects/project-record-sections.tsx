import { useTranslation } from 'react-i18next'
import { CalendarRange, FolderKanban, Globe, Tags } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { useCascadeEditable } from '@/components/record-form/use-cascade-editable'
import { Badge } from '@/components/ui/badge'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import { formatDecimal } from '@/features/products/column-renderers'
import {
  ProjectCodeField,
  ProjectDateField,
  ProjectDescriptionField,
  ProjectGeoField,
  ProjectNameField,
  ProjectNumberField,
  ProjectOperationalSiteField,
  ProjectPartnerField,
  ProjectProductLinesField,
  ProjectStatusField,
} from '@/features/projects/project-fields'
import {
  PROJECT_GEO_FIELDS,
  type ProjectRecordSectionProps,
  type ProjectRecordValues,
} from '@/features/projects/project-record'
import { statusBadgeClassName } from '@/features/projects/status-badge-classes'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

function TextValue({ value }: { value: string | null }) {
  return value !== null && value.trim() !== '' ? (
    <span className="break-words whitespace-pre-wrap">{value}</span>
  ) : (
    <DetailEmpty />
  )
}

/** "Anagrafica": code, name, description. The code edits on create only (spec 0220 D-2). */
export function ProjectIdentityRecordSection({ values, form, inline }: ProjectRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('projects.form.sections.identity.title')} icon={<FolderKanban />}>
      <RecordFieldList>
        <RecordInlineField
          field="code"
          label={t('projects.form.code')}
          inline={inline}
          editor={<ProjectCodeField control={control} />}
        >
          <TextValue value={values.code} />
        </RecordInlineField>
        <RecordInlineField
          field="name"
          label={t('projects.form.name')}
          inline={inline}
          editor={<ProjectNameField control={control} />}
        >
          <TextValue value={values.name} />
        </RecordInlineField>
        <RecordInlineField
          field="description"
          label={t('projects.form.description')}
          inline={inline}
          editor={<ProjectDescriptionField control={control} />}
        >
          <TextValue value={values.description} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** The status as the record shows it: its coloured badge once the color is known, the bare name on a draft. */
function StatusValue({ status }: { status: ProjectRecordValues['pipeline_status'] }) {
  if (!status) {
    return <DetailEmpty />
  }
  const color = 'color' in status ? status.color : null
  return (
    <Badge variant="secondary" className={cn(statusBadgeClassName(color))}>
      {status.name}
    </Badge>
  )
}

/** The classification rows: the persisted list on the detail, the draft's "Root > Category" labels on create. */
function ProductLinesValue({ values }: { values: ProjectRecordValues }) {
  if (values.product_lines !== null) {
    return <ProductLinesReadOnlyList lines={values.product_lines} />
  }
  if (values.product_line_labels.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-0.5 text-sm">
      {values.product_line_labels.map((label, index) => (
        // A draft row has no id yet: its position is its identity.
        <li key={index}>{label}</li>
      ))}
    </ul>
  )
}

/**
 * "Classificazione": status, partner (a referente, linked to its own module),
 * Sede and the product lines. The status is also the identity band's badge:
 * the row is where it changes.
 */
export function ProjectClassificationRecordSection({ values, form, inline }: ProjectRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const site = values.operational_site

  return (
    <RecordSection title={t('projects.form.sections.classification.title')} icon={<Tags />}>
      <RecordFieldList>
        <RecordInlineField
          field="pipeline_status_id"
          label={t('projects.form.status')}
          inline={inline}
          editor={
            <ProjectStatusField
              control={control}
              selected={values.pipeline_status ? { id: values.pipeline_status.id, name: values.pipeline_status.name } : null}
            />
          }
        >
          <StatusValue status={values.pipeline_status} />
        </RecordInlineField>
        <RecordInlineField
          field="partner_id"
          label={t('projects.form.partner')}
          inline={inline}
          editor={<ProjectPartnerField control={control} selected={values.partner} />}
        >
          {values.partner ? (
            <RecordLink domain="referents" id={values.partner.id}>
              {values.partner.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="operational_site_id"
          label={t('projects.form.operationalSite')}
          inline={inline}
          editor={
            <ProjectOperationalSiteField control={control} selected={site ? { id: site.id, name: site.label } : null} />
          }
        >
          {site && site.label ? (
            <RecordLink domain="operational-sites" id={site.id}>
              {site.label}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="product_lines"
          label={t('projects.form.productLines')}
          inline={inline}
          editor={<ProjectProductLinesField control={control} />}
        >
          <ProductLinesValue values={values} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Area geografica" (spec 0027 BR-4): the four levels shown one per line, ONE
 * row edited as one cascade — a level depends on its parent, so they change
 * together. Editable only when all four levels are.
 */
export function ProjectGeographyRecordSection({ values, form, inline }: ProjectRecordSectionProps) {
  const { t } = useTranslation()
  const editable = useCascadeEditable(PROJECT_GEO_FIELDS)
  const levels = [
    { key: 'country', label: t('geo.country'), value: values.country },
    { key: 'state', label: t('geo.state'), value: values.state },
    { key: 'province', label: t('geo.province'), value: values.province },
    { key: 'city', label: t('geo.city'), value: values.city },
  ]
  const title = t('projects.form.sections.geography.title')

  return (
    <RecordSection title={title} icon={<Globe />}>
      <RecordInlineField
        field="country_id"
        label={title}
        inline={inline}
        layout="block"
        canEdit={editable}
        editor={<ProjectGeoField control={form.control} setValue={form.setValue} />}
      >
        <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
          {levels.map((level) => (
            <div key={level.key} className="contents">
              <dt className="text-muted-foreground">{level.label}</dt>
              <dd className="min-w-0 truncate">{level.value?.name ?? <DetailEmpty />}</dd>
            </div>
          ))}
        </dl>
      </RecordInlineField>
    </RecordSection>
  )
}

/** "Pianificazione": dates, total budget and target leads. */
export function ProjectPlanningRecordSection({ values, form, inline }: ProjectRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('projects.form.sections.planning.title')} icon={<CalendarRange />} full>
      <RecordFieldList>
        <RecordInlineField
          field="start_date"
          label={t('projects.form.startDate')}
          inline={inline}
          editor={<ProjectDateField control={control} name="start_date" label={t('projects.form.startDate')} />}
        >
          {formatDate(values.start_date) || <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="end_date"
          label={t('projects.form.endDate')}
          inline={inline}
          editor={<ProjectDateField control={control} name="end_date" label={t('projects.form.endDate')} />}
        >
          {formatDate(values.end_date) || <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="total_budget"
          label={t('projects.form.totalBudget')}
          inline={inline}
          editor={
            <ProjectNumberField
              control={control}
              name="total_budget"
              label={t('projects.form.totalBudget')}
              hint={t('projects.form.hints.totalBudget')}
              step="0.01"
            />
          }
        >
          {values.total_budget !== null ? formatDecimal(values.total_budget) : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="target_lead"
          label={t('projects.form.targetLead')}
          inline={inline}
          editor={
            <ProjectNumberField
              control={control}
              name="target_lead"
              label={t('projects.form.targetLead')}
              hint={t('projects.form.hints.targetLead')}
              step="1"
            />
          }
        >
          {values.target_lead ?? <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
