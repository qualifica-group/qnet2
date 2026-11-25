import { GeoField, type GeoFieldProps } from '@/features/geo/geo-field'

interface GeoCompactFieldsProps {
  country: GeoFieldProps
  state: GeoFieldProps
  province: GeoFieldProps
  city: GeoFieldProps
}

/**
 * Compact layout of the geo cascade: nazione/regione/provincia side by side on
 * one row, the comune below. The comune stays searchable on its own and
 * backfills its ancestors (see `GeoSelect`).
 *
 * Every level is always visible, never behind a disclosure (user directive
 * 2026-11-25, supersedes the 2026-09-07 collapsed ancestors): a hidden Nazione
 * meant nobody filled in a foreign address.
 */
export function GeoCompactFields({ country, state, province, city }: GeoCompactFieldsProps) {
  return (
    <div className="flex flex-col gap-3">
      <div className="grid gap-3 sm:grid-cols-3">
        <GeoField {...country} />
        <GeoField {...state} />
        <GeoField {...province} />
      </div>
      <GeoField {...city} />
    </div>
  )
}
