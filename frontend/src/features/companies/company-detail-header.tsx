import { useTranslation } from 'react-i18next'
import { Building2, Globe, Map, MapPinned, Receipt } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import type { CompanyAddress, CompanyDetailWithPermissions } from '@/features/companies/types'

/**
 * Identity band and KPI strip of the company record card. Kept in one file:
 * both pieces read the same handful of top-level fields and are always mounted
 * together — the same split `opportunity-detail-header.tsx` makes.
 */

interface CompanyDetailHeaderProps {
  company: CompanyDetailWithPermissions
}

/** Identity band: monogram, denomination and a short "City, Country" subtitle (the rows below edit in place). */
export function CompanyDetailHeader({ company }: CompanyDetailHeaderProps) {
  return (
    <RecordCardHeader
      media={
        <DetailMonogram
          name={company.denomination}
          icon={<Building2 />}
          className="size-10 text-base [&>svg]:size-5"
        />
      }
      title={company.denomination}
      legacyId={company.old_id}
      subtitle={locationSummary(company.address)}
    />
  )
}

interface CompanyDetailStatsProps {
  company: CompanyDetailWithPermissions
}

/**
 * KPI strip: the fiscal identity plus WHERE the company sits, comune-first —
 * the same order every address surface reads in (directive 2026-09-10). The
 * street and the CAP stay in the section below: they are the address of record,
 * not the at-a-glance answer.
 */
export function CompanyDetailStats({ company }: CompanyDetailStatsProps) {
  const { t } = useTranslation()
  const address = company.address

  return (
    <RecordStatStrip>
      <RecordStat
        label={t('companies.form.vatNumber')}
        icon={<Receipt />}
        value={company.vat_number || <DetailEmpty />}
      />
      <RecordStat
        label={t('companies.detail.city')}
        icon={<MapPinned />}
        value={address?.city || <DetailEmpty />}
      />
      <RecordStat
        label={t('companies.detail.province')}
        icon={<Map />}
        value={address?.province || <DetailEmpty />}
      />
      <RecordStat
        label={t('companies.detail.country')}
        icon={<Globe />}
        value={address?.country || <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}

/** A short "City, Country" line for the identity subtitle, or undefined. */
function locationSummary(address: CompanyAddress | null): string | undefined {
  if (!address) {
    return undefined
  }
  const summary = [address.city, address.country].filter(Boolean).join(', ')
  return summary || undefined
}
