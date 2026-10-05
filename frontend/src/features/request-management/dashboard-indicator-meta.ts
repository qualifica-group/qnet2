import {
  Building2,
  CalendarCheck,
  ChartColumn,
  Handshake,
  type LucideIcon,
  Phone,
  PhoneMissed,
  Rocket,
  School,
  Send,
  Target,
  UserCheck,
  UserPlus,
} from 'lucide-react'

/**
 * Icon per indicator key of `config('request-management-report.indicator_columns')`
 * (spec 0192 D-7). The columns are configurable per category, so a key not
 * listed here falls back to a generic chart icon instead of failing.
 */
export const INDICATOR_ICONS: Partial<Record<string, LucideIcon>> = {
  telefonate: Phone,
  unhandled_callbacks: PhoneMissed,
  unhandled_new_contacts: UserPlus,
  current_potentials: Target,
  richiami: PhoneMissed,
  nuovi_contatti: UserPlus,
  potenziali: Target,
  aule_gestione: School,
  aule_partenza: Rocket,
  associati: UserCheck,
  aziende_inserite: Building2,
  presa_appuntamenti: CalendarCheck,
  trattative_concluse: Handshake,
  invio_presa_in_carico: Send,
}

export const FALLBACK_INDICATOR_ICON: LucideIcon = ChartColumn


/**
 * Accent color per indicator key, grouped by meaning so related columns read
 * alike: activity, unhandled work (amber, like the KPI accent), pipeline,
 * classes, outcomes. Theme variables only, so dark mode follows. Used for
 * fills and tints, never as the color of text (spec 0152 D-4: the lighter
 * series colors fail AA as text on a card).
 */
export const INDICATOR_COLORS: Partial<Record<string, string>> = {
  telefonate: 'var(--chart-1)',
  presa_appuntamenti: 'var(--chart-1)',
  unhandled_callbacks: 'var(--warning)',
  unhandled_new_contacts: 'var(--warning)',
  richiami: 'var(--warning)',
  nuovi_contatti: 'var(--warning)',
  current_potentials: 'var(--chart-2)',
  potenziali: 'var(--chart-2)',
  aziende_inserite: 'var(--chart-2)',
  aule_gestione: 'var(--chart-3)',
  aule_partenza: 'var(--chart-3)',
  associati: 'var(--success)',
  trattative_concluse: 'var(--success)',
  invio_presa_in_carico: 'var(--success)',
}

export const FALLBACK_INDICATOR_COLOR = 'var(--primary)'

/** Share of the indicator color in a value's tinted pill: soft enough for foreground text to keep AA. */
const VALUE_TINT_PERCENT = 18

export function indicatorTint(color: string): string {
  return `color-mix(in oklab, ${color} ${VALUE_TINT_PERCENT}%, transparent)`
}
