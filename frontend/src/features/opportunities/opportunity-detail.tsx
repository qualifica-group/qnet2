import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import {
  Building2,
  ClipboardList,
  Contact,
  Handshake,
  History,
  Paperclip,
  StickyNote,
  Users,
} from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailPerson,
  DetailSection,
} from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDecimal } from '@/features/products/column-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { OPPORTUNITY_STATUS_BADGE_CLASSES } from '@/features/opportunities/column-renderers'
import { StatusDescriptionHint } from '@/features/opportunity-workflows/status-description-hint'
import type {
  ApplicableAttributeSummary,
  OpportunityDetailWithPermissions as OpportunityDetailData,
  OpportunityProductLine,
  OpportunityProductOfInterest,
} from '@/features/opportunities/types'

/** Stable empty defaults (spec 0049 D-8): a missing key on older fixtures reads the same as `[]`/`{}`. */
const EMPTY_APPLICABLE_ATTRIBUTES: ApplicableAttributeSummary[] = []
const EMPTY_ATTRIBUTE_VALUES: Record<string, unknown> = {}
const EMPTY_PRODUCTS_OF_INTEREST: OpportunityProductOfInterest[] = []

interface OpportunityDetailViewProps {
  opportunity: OpportunityDetailData
}

/** Read-only list of the opportunity's business-function + product-category rows (spec 0040 amendment rev.3, AC-101). */
function ProductLinesList({ lines }: { lines: OpportunityProductLine[] }) {
  if (lines.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-1">
      {lines.map((line) => (
        <li key={line.id}>
          <span className="font-medium">{line.business_function.name}</span>
          <span className="text-muted-foreground"> — {line.product_category.name}</span>
        </li>
      ))}
    </ul>
  )
}

/**
 * Read-only list of the opportunity's "prodotti di interesse" (user directive
 * 2026-07-22), each with the category it belongs to — the same pairing the
 * picker shows while selecting them.
 */
function ProductsOfInterestList({ products }: { products: OpportunityProductOfInterest[] }) {
  if (products.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-1">
      {products.map((product) => (
        <li key={product.id}>
          <span className="font-medium">{product.name}</span>
          {product.product_category ? (
            <span className="text-muted-foreground"> — {product.product_category.name}</span>
          ) : null}
        </li>
      ))}
    </ul>
  )
}

/**
 * Formats one collected attribute value for read-only display (spec 0049
 * D-8), per the Attribute's shared type vocabulary. Returns `null` for an
 * unset value so the caller can fall back to `DetailEmpty`.
 */
function formatAttributeValue(
  attribute: ApplicableAttributeSummary,
  rawValue: unknown,
  t: TFunction,
): string | null {
  if (rawValue === null || rawValue === undefined || rawValue === '') {
    return null
  }
  if (Array.isArray(rawValue)) {
    const items = rawValue
      .map((item) => formatAttributeScalar(attribute, item, t))
      .filter((item): item is string => item !== null)
    return items.length > 0 ? items.join(', ') : null
  }
  return formatAttributeScalar(attribute, rawValue, t)
}

/** Formats a single (non-array) attribute value, dispatching on `type`. */
function formatAttributeScalar(
  attribute: ApplicableAttributeSummary,
  rawValue: unknown,
  t: TFunction,
): string | null {
  if (rawValue === null || rawValue === undefined || rawValue === '') {
    return null
  }
  switch (attribute.type) {
    case 'boolean':
      return rawValue ? t('common.yes') : t('common.no')
    case 'enum': {
      const option = attribute.options.find((candidate) => candidate.value === String(rawValue))
      return option?.label ?? String(rawValue)
    }
    case 'datetime':
      return formatDateTime(rawValue) || String(rawValue)
    case 'relation': {
      if (rawValue && typeof rawValue === 'object') {
        const relation = rawValue as { label?: unknown; name?: unknown; id?: unknown }
        if (typeof relation.label === 'string') return relation.label
        if (typeof relation.name === 'string') return relation.name
        if (relation.id !== undefined) return String(relation.id)
      }
      return String(rawValue)
    }
    default:
      return String(rawValue)
  }
}

/**
 * Read-only "Collected information" section (spec 0049 D-8, AC-064): one
 * `DetailField` per applicable Attribute, its value formatted per `type`.
 * Absent entirely when the opportunity has no applicable Attribute (no
 * product-category row defines any).
 */
function CollectedAttributesSection({
  attributes,
  values,
}: {
  attributes: ApplicableAttributeSummary[]
  values: Record<string, unknown>
}) {
  const { t } = useTranslation()
  if (attributes.length === 0) {
    return null
  }
  return (
    <DetailSection title={t('opportunities.detail.collectedInformation')} icon={<ClipboardList />}>
      <DetailGrid>
        {attributes.map((attribute) => (
          <DetailField key={attribute.id} label={attribute.name}>
            {formatAttributeValue(attribute, values[attribute.code], t) ?? <DetailEmpty />}
          </DetailField>
        ))}
      </DetailGrid>
    </DetailSection>
  )
}

/** Formats a `Y-m-d` date column, blank when missing/invalid — mirrors the column renderer. */
function formatDate(value: string | null): string | null {
  if (!value) {
    return null
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString()
}

/**
 * Derives the attachments abilities and renders the documents section. Kept
 * as its own mount (rather than calling `useAbilities()` unconditionally in
 * `OpportunityDetailView`) so the underlying `useQuery` calls only run while
 * the section is actually authorized to render.
 */
function OpportunityDocumentsPanel({ opportunityId }: { opportunityId: number }) {
  const { can } = useAbilities()
  return (
    <DocumentsSection
      resource="opportunity"
      id={opportunityId}
      canUpload={can('attachments.create')}
      canDelete={can('attachments.delete')}
    />
  )
}

/**
 * Read-only detail of a single opportunity, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered by the dedicated detail page (spec 0040, mirrors leads).
 */
export function OpportunityDetailView({ opportunity }: OpportunityDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(opportunity.created_at)
  const startDate = formatDate(opportunity.start_date)
  const expectedCloseDate = formatDate(opportunity.expected_close_date)
  const estimatedValue = formatDecimal(opportunity.estimated_value)
  const sortedManagers = [...opportunity.managers].sort((a, b) => a.position - b.position)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={opportunity.name} icon={<Handshake />} />}
        title={opportunity.name}
        subtitle={opportunity.registry?.name}
      />

      <DetailSection title={t('opportunities.form.sections.identity.title')} icon={<Contact />}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.registry')}>
            {opportunity.registry?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.opportunityStatus')}>
            <Badge
              variant="secondary"
              className={cn(
                'h-5 min-h-5',
                opportunity.opportunity_status.color
                  ? OPPORTUNITY_STATUS_BADGE_CLASSES[opportunity.opportunity_status.color]
                  : undefined,
              )}
            >
              {opportunity.opportunity_status.name}
            </Badge>
          </DetailField>
          <DetailField label={t('opportunities.form.referent')}>
            {opportunity.referent?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.commercial')}>
            {opportunity.commercial?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.reporter')}>
            {opportunity.reporter?.name ?? <DetailEmpty />}
          </DetailField>
          {opportunity.lead ? (
            <DetailField label={t('opportunities.detail.sourceLead')}>{opportunity.lead.label}</DetailField>
          ) : null}
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('opportunities.form.sections.classification.title')} icon={<Building2 />}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.source')}>
            {opportunity.source?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.operationalSite')}>
            {opportunity.operational_site?.label ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.state')}>
            {opportunity.state?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.workflowStatus')}>
            {opportunity.workflow_status ? (
              <span className="flex min-w-0 items-center gap-1">
                <Badge variant="secondary" className="h-5 min-h-5 gap-1.5">
                  <span
                    className={cn(
                      'size-1.5 shrink-0 rounded-full',
                      swatchClassFor(opportunity.workflow_status.color) ?? 'bg-transparent',
                    )}
                    aria-hidden="true"
                  />
                  {opportunity.workflow_status.name}
                </Badge>
                <StatusDescriptionHint description={opportunity.workflow_status.description} />
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('opportunities.form.sections.productLines.title')} full>
            <ProductLinesList lines={opportunity.product_lines} />
          </DetailField>
          <DetailField label={t('products.ofInterest.sectionTitle')} full>
            <ProductsOfInterestList products={opportunity.products_of_interest ?? EMPTY_PRODUCTS_OF_INTEREST} />
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('opportunities.form.sections.team.title')} icon={<Users />}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.supervisor')}>
            {opportunity.supervisor?.name ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
        <div className="mt-4 flex flex-col gap-2">
          <span className="text-xs font-medium text-muted-foreground">{t('opportunities.form.managers')}</span>
          {sortedManagers.length > 0 ? (
            <ul className="flex flex-col gap-2">
              {sortedManagers.map((manager) => (
                <li key={manager.id} className="flex items-center gap-2">
                  <span className="w-5 shrink-0 text-xs font-semibold text-muted-foreground">
                    {manager.position}
                  </span>
                  <DetailPerson name={manager.name} />
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </div>
      </DetailSection>

      <DetailSection title={t('opportunities.form.sections.planning.title')}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.startDate')}>{startDate ?? <DetailEmpty />}</DetailField>
          <DetailField label={t('opportunities.form.expectedCloseDate')}>
            {expectedCloseDate ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.estimatedValue')}>
            {estimatedValue || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.successProbability')}>
            {opportunity.success_probability !== null ? `${opportunity.success_probability}%` : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('opportunities.form.sections.generalNotes.title')} icon={<StickyNote />}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.generalNotes')} full>
            {opportunity.general_notes ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <CollectedAttributesSection
        attributes={opportunity.applicable_attributes ?? EMPTY_APPLICABLE_ATTRIBUTES}
        values={opportunity.attribute_values ?? EMPTY_ATTRIBUTE_VALUES}
      />

      {opportunity.permissions.actions.view_documents ? (
        <DetailSection title={t('attachments.title')} icon={<Paperclip />}>
          <OpportunityDocumentsPanel opportunityId={opportunity.id} />
        </DetailSection>
      ) : null}

      {opportunity.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="opportunities" id={opportunity.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('opportunities.columns.createdAt')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
