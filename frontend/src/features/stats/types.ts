/**
 * Contract of the backend-driven module statistics (spec 0026).
 * `GET /api/stats/{domain}` returns a list of widgets; the panel is generic and
 * knows nothing about any module. Discriminated union on `type`.
 *
 * Every widget `label` is an i18n KEY resolved by the frontend (D-4). The
 * `label` of a distribution ITEM is a domain value read from the database and
 * is rendered verbatim.
 */

/**
 * How a `stat` value must be rendered. `percent` is already 0..100 server-side;
 * `duration` is whole minutes (spec 0147).
 */
export type StatValueFormat = 'number' | 'currency' | 'percent' | 'duration'

/** A trend never carries percentages: only absolute or monetary values. */
export type TrendValueFormat = 'number' | 'currency'

export interface StatSubtitle {
  /** i18n key, interpolated with `count`. */
  key: string
  count: number
}

export interface StatWidget {
  type: 'stat'
  key: string
  label: string
  /** `null` = value not available (e.g. a percent with a 0 denominator): never render "0%". */
  value: number | null
  format: StatValueFormat
  subtitle: StatSubtitle | null
  /** Lucide icon name from the frontend allow-list; unknown names render no icon. */
  icon: string | null
}

export interface DistributionItem {
  key: string
  /** Domain text from the database (source name, status name…): NOT an i18n key. */
  label: string
  value: number
  color: string | null
}

/**
 * Shape a `distribution` widget renders as (spec 0152 D-2). Chosen once by the
 * backend definition; absent or unrecognized -> `bars` (contract default).
 */
export type DistributionChartVariant = 'bars' | 'columns' | 'donut' | 'stacked'

export interface DistributionWidget {
  type: 'distribution'
  key: string
  label: string
  items: DistributionItem[]
  /** Denominator of the percentages (0 allowed). */
  total: number
  /** Optional (spec 0152 D-1): absent/unknown -> `bars`. */
  chart?: string
}

export interface TrendPoint {
  label: string
  value: number
}

/**
 * Shape a `trend` widget renders as (spec 0152 D-3). Chosen once by the
 * backend definition; absent or unrecognized -> `area` (contract default).
 */
export type TrendChartVariant = 'area' | 'columns' | 'line'

/** `--chart-{tone}` theme slot (spec 0152 D-4). Absent/out of range -> `1`. */
export type ChartTone = 1 | 2 | 3 | 4 | 5

export interface TrendWidget {
  type: 'trend'
  key: string
  label: string
  points: TrendPoint[]
  format: TrendValueFormat
  /** Optional (spec 0152 D-1): absent/unknown -> `area`. */
  chart?: string
  /** Optional (spec 0152 D-1): absent/out of range -> `1`. */
  tone?: number
}

export type StatsWidget = StatWidget | DistributionWidget | TrendWidget

/** `data` payload of the endpoint envelope. An empty list is a valid empty state. */
export interface ModuleStats {
  widgets: StatsWidget[]
}
