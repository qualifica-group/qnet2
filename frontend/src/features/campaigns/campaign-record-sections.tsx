import { useTranslation } from 'react-i18next'
import { CalendarRange, FolderKanban, Globe, Megaphone, Tags } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { useCascadeEditable } from '@/components/record-form/use-cascade-editable'
import { Badge } from '@/components/ui/badge'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import { formatDecimal } from '@/features/products/column-renderers'
import { statusBadgeClassName } from '@/features/projects/status-badge-classes'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'
import { GEO_LEVELS } from '@/features/campaigns/campaign-geo'
import {
  CampaignCodeField,
  CampaignDateField,
  CampaignDescriptionField,
  CampaignGeoField,
  CampaignNameField,
  CampaignNumberField,
  CampaignPartnerField,
  CampaignProductLinesField,
  CampaignSiteField,
  CampaignSourceField,
  CampaignStatusField,
} from '@/features/campaigns/campaign-fields'
import { CampaignProjectEditor } from '@/features/campaigns/campaign-project-editor'
import {
  CAMPAIGN_GEO_FIELDS,
  PROJECT_CASCADE_FIELDS,
  type CampaignRecordSectionProps,
  type CampaignRecordValues,
} from '@/features/campaigns/campaign-record'

function TextValue({ value }: { value: string | null }) {
  return value !== null && value.trim() !== '' ? (
    <span className="break-words whitespace-pre-wrap">{value}</span>
  ) : (
    <DetailEmpty />
  )
}

/** "Anagrafica": code, name, description. The code edits on create only (spec 0221 D-2). */
export function CampaignIdentityRecordSection({ values, form, inline }: CampaignRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('campaigns.form.sections.identity.title')} icon={<Megaphone />}>
      <RecordFieldList>
        <RecordInlineField field="code" label={t('campaigns.form.code')} inline={inline} editor={<CampaignCodeField control={control} />}>
          <TextValue value={values.code} />
        </RecordInlineField>
        <RecordInlineField field="name" label={t('campaigns.form.name')} inline={inline} editor={<CampaignNameField control={control} />}>
          <TextValue value={values.name} />
        </RecordInlineField>
        <RecordInlineField
          field="description"
          label={t('campaigns.form.description')}
          inline={inline}
          editor={<CampaignDescriptionField control={control} />}
        >
          <TextValue value={values.description} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Collegamento al progetto": the project, Partner, Sede and Fonte, each a
 * link to its own module. A Project pick prefills a whole cascade (D-3): its
 * row opens only when every field it writes is editable.
 */
export function CampaignProjectRecordSection({ values, form, inline }: CampaignRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const projectCascadeEditable = useCascadeEditable(PROJECT_CASCADE_FIELDS)
  const site = values.operational_site

  return (
    <RecordSection title={t('campaigns.form.sections.project.title')} icon={<FolderKanban />}>
      <RecordFieldList>
        <RecordInlineField
          field="project_id"
          label={t('campaigns.form.project')}
          inline={inline}
          canEdit={projectCascadeEditable}
          editor={<CampaignProjectEditor form={form} selected={values.project} />}
        >
          {values.project ? (
            <RecordLink domain="projects" id={values.project.id}>
              {values.project.label}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        {/* A `Referent`, NOT a User: a link to its own module, never the user hover card. */}
        <RecordInlineField
          field="partner_id"
          label={t('campaigns.form.partner')}
          inline={inline}
          editor={<CampaignPartnerField control={control} selected={values.partner} />}
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
          label={t('campaigns.form.operationalSite')}
          inline={inline}
          editor={<CampaignSiteField control={control} selected={site ? { id: site.id, name: site.label } : null} />}
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
          field="source_id"
          label={t('campaigns.form.source')}
          inline={inline}
          editor={<CampaignSourceField control={control} selected={values.source} />}
        >
          {values.source?.name ?? <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** The status as the record shows it: its coloured badge once the color is known, the bare name on a draft. */
function StatusValue({ status }: { status: CampaignRecordValues['pipeline_status'] }) {
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
function ProductLinesValue({ values }: { values: CampaignRecordValues }) {
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
 * "Classificazione": status and product lines — the campaign's own while
 * standalone, the linked project's (no pencil) otherwise (BR-2, D-5).
 */
export function CampaignClassificationRecordSection({ values, form, inline }: CampaignRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const linked = values.project !== null
  const status = values.pipeline_status

  return (
    <RecordSection title={t('campaigns.form.sections.classification.title')} icon={<Tags />}>
      {linked ? <p className="text-xs text-muted-foreground">{t('campaigns.form.hints.classification')}</p> : null}
      <RecordFieldList>
        <RecordInlineField
          field="pipeline_status_id"
          label={t('campaigns.form.status')}
          inline={inline}
          canEdit={!linked}
          editor={<CampaignStatusField control={control} selected={status ? { id: status.id, name: status.name } : null} />}
        >
          <StatusValue status={status} />
        </RecordInlineField>
        <RecordInlineField
          field="product_lines"
          label={t('campaigns.form.productLines')}
          inline={inline}
          canEdit={!linked}
          editor={<CampaignProductLinesField control={control} />}
        >
          <ProductLinesValue values={values} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Area geografica" (spec 0027 BR-4/BR-5): the four levels shown one per line,
 * ONE row edited as one cascade. Editable when all four levels are and the
 * linked project leaves at least one of them to the campaign.
 */
export function CampaignGeographyRecordSection({ values, form, inline }: CampaignRecordSectionProps) {
  const { t } = useTranslation()
  const permitted = useCascadeEditable(CAMPAIGN_GEO_FIELDS)
  const ownsALevel = values.geo_locked_levels.length < GEO_LEVELS.length
  const title = t('campaigns.form.sections.geography.title')
  const levels = GEO_LEVELS.map((level) => ({ level, label: t(`geo.${level}`), value: values[level] }))

  return (
    <RecordSection title={title} icon={<Globe />}>
      <RecordInlineField
        field="country_id"
        label={title}
        inline={inline}
        layout="block"
        canEdit={permitted && ownsALevel}
        editor={<CampaignGeoField control={form.control} setValue={form.setValue} />}
      >
        <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
          {levels.map(({ level, label, value }) => (
            <div key={level} className="contents">
              <dt className="text-muted-foreground">{label}</dt>
              <dd className="min-w-0 truncate">{value?.name ?? <DetailEmpty />}</dd>
            </div>
          ))}
        </dl>
      </RecordInlineField>
      {values.geo_locked_levels.length > 0 ? (
        <p className="text-xs text-muted-foreground">{t('campaigns.form.geoInheritedFromProject')}</p>
      ) : null}
    </RecordSection>
  )
}

/** "Pianificazione": dates, total budget (with the project's residual while editing) and target leads. */
export function CampaignPlanningRecordSection({ values, form, inline }: CampaignRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('campaigns.form.sections.planning.title')} icon={<CalendarRange />} full>
      <RecordFieldList>
        <RecordInlineField
          field="start_date"
          label={t('campaigns.form.startDate')}
          inline={inline}
          editor={<CampaignDateField control={control} name="start_date" label={t('campaigns.form.startDate')} />}
        >
          {formatDate(values.start_date) || <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="end_date"
          label={t('campaigns.form.endDate')}
          inline={inline}
          editor={<CampaignDateField control={control} name="end_date" label={t('campaigns.form.endDate')} />}
        >
          {formatDate(values.end_date) || <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="total_budget"
          label={t('campaigns.form.totalBudget')}
          inline={inline}
          editor={
            <CampaignNumberField control={control} name="total_budget" label={t('campaigns.form.totalBudget')} step="0.01" />
          }
        >
          {values.total_budget !== null ? formatDecimal(values.total_budget) : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="target_lead"
          label={t('campaigns.form.targetLead')}
          inline={inline}
          editor={<CampaignNumberField control={control} name="target_lead" label={t('campaigns.form.targetLead')} step="1" />}
        >
          {values.target_lead ?? <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
