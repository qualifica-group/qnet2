import { useTranslation } from 'react-i18next'
import { FileText, FolderKanban, Globe, Tags } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import type { CampaignDetailWithPermissions as CampaignDetailData } from '@/features/campaigns/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

interface CampaignDetailSectionsProps {
  campaign: CampaignDetailData
}

/**
 * The record's `RecordSectionsGrid` body, named after the module's OWN
 * `form.sections.*` like every other detail in the app: the project, the
 * Partner and the Sede live in "Collegamento al progetto" — the section that
 * already owned them — simply rendered as links, rather than pulled out into a
 * separate "links" group (user directive 2026-09-11).
 *
 * The 4 geo levels always show the EFFECTIVE (merged) tuple (BR-5, spec 0027);
 * `CampaignResource` resolves it server-side, so nothing here special-cases
 * `derived_from_project` beyond the inherited-levels hint.
 */
export function CampaignDetailSections({ campaign }: CampaignDetailSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      {campaign.description ? (
        <RecordSection
          title={t('campaigns.form.description')}
          icon={<FileText />}
          className={FULL_WIDTH_SECTION_CLASS}
        >
          {/* `whitespace-pre-wrap`: a pasted multi-line brief keeps its shape. */}
          <p className="text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground">
            {campaign.description}
          </p>
        </RecordSection>
      ) : null}

      <RecordSection title={t('campaigns.form.sections.project.title')} icon={<FolderKanban />}>
        <RecordFieldList>
          <RecordField label={t('campaigns.form.project')}>
            {campaign.project ? (
              <RecordLink domain="projects" id={campaign.project.id}>
                {`${campaign.project.code} — ${campaign.project.name}`}
              </RecordLink>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>

          {/*
            The Partner is a `Referent`, NOT a User — so it gets a `RecordLink`
            to its own module, never `UserProfileHoverCard` (which opens the
            shared USER detail Sheet and would resolve the wrong record).
          */}
          <RecordField label={t('campaigns.form.partner')}>
            {campaign.partner ? (
              <RecordLink domain="referents" id={campaign.partner.id}>
                {campaign.partner.name}
              </RecordLink>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>

          <RecordField label={t('campaigns.form.operationalSite')}>
            {campaign.operational_site && campaign.operational_site.label ? (
              <RecordLink domain="operational-sites" id={campaign.operational_site.id}>
                {campaign.operational_site.label}
              </RecordLink>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      {/* The pipeline status is NOT repeated here: it is the badge in the
          identity band, the way Opportunita' keeps its own. */}
      <RecordSection title={t('campaigns.form.sections.classification.title')} icon={<Tags />}>
        <RecordFieldList>
          <RecordField label={t('campaigns.form.productLines')}>
            <ProductLinesReadOnlyList lines={campaign.product_lines} />
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('campaigns.form.sections.geography.title')} icon={<Globe />}>
        <RecordFieldList>
          <RecordField label={t('geo.country')}>
            {campaign.country?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('geo.state')}>{campaign.state?.name ?? <DetailEmpty />}</RecordField>
          <RecordField label={t('geo.province')}>
            {campaign.province?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('geo.city')}>{campaign.city?.name ?? <DetailEmpty />}</RecordField>
        </RecordFieldList>
        {campaign.geo_locked_levels.length > 0 ? (
          <p className="text-xs text-muted-foreground">
            {t('campaigns.form.geoInheritedFromProject')}
          </p>
        ) : null}
      </RecordSection>
    </RecordSectionsGrid>
  )
}
