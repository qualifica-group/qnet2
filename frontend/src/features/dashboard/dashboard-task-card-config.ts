import { Eye, ListChecks, Pencil, Plus, UserCheck, type LucideIcon } from 'lucide-react'
import type { DashboardTaskCounterKey } from '@/features/dashboard/types'

/** Fixed display order of the cards (spec 0151 D-9, reference q-net order). */
export const DASHBOARD_TASK_CARD_ORDER: readonly DashboardTaskCounterKey[] = [
  'not_completed',
  'assigned_to_me',
  'assigned_by_me',
  'created_by_me',
  'observed_by_me',
]

/** q-net's task visibility icons (`TASK_VISIBILITY_ICONS`), lucide equivalents of the tabler set. */
export const DASHBOARD_TASK_CARD_ICONS: Record<DashboardTaskCounterKey, LucideIcon> = {
  not_completed: ListChecks,
  assigned_to_me: UserCheck,
  assigned_by_me: Plus,
  created_by_me: Pencil,
  observed_by_me: Eye,
}

export interface DashboardTaskCardTone {
  title: string
  accent: string
  gradient: string
  iconBox: string
  chip: string
}

/**
 * q-net's per-card hues (slate / emerald / sky / violet / amber), kept as the
 * same Tailwind palette the BadgeTokens colors already use in this repo
 * (`status-badge-classes.ts`), with explicit dark variants.
 */
export const DASHBOARD_TASK_CARD_TONES: Record<DashboardTaskCounterKey, DashboardTaskCardTone> = {
  not_completed: {
    title: 'text-slate-700 dark:text-slate-300',
    accent: 'border-l-slate-400/40',
    gradient: 'from-slate-500/10',
    iconBox: 'border-slate-400/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    chip: 'border-slate-400/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
  },
  assigned_to_me: {
    title: 'text-emerald-700 dark:text-emerald-300',
    accent: 'border-l-emerald-500/40',
    gradient: 'from-emerald-500/10',
    iconBox: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    chip: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
  },
  assigned_by_me: {
    title: 'text-sky-700 dark:text-sky-300',
    accent: 'border-l-sky-500/40',
    gradient: 'from-sky-500/10',
    iconBox: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    chip: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
  },
  created_by_me: {
    title: 'text-violet-700 dark:text-violet-300',
    accent: 'border-l-violet-500/40',
    gradient: 'from-violet-500/10',
    iconBox: 'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    chip: 'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-300',
  },
  observed_by_me: {
    title: 'text-amber-700 dark:text-amber-300',
    accent: 'border-l-amber-500/40',
    gradient: 'from-amber-500/10',
    iconBox: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    chip: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
  },
}

/** The "da validare" chip keeps q-net's amber tint on every card. */
export const DASHBOARD_TASK_TO_VALIDATE_CHIP_CLASS =
  'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'

/** `<frontend_links>` of spec 0151: one-time, non-persisted `/tasks` filters (D-2). */
export const DASHBOARD_TASK_CARD_HREFS: Record<DashboardTaskCounterKey, string> = {
  not_completed: '/tasks?status=open',
  assigned_to_me: '/tasks?status=open&assignment=assigned_to_me',
  assigned_by_me: '/tasks?status=open&assignment=assigned_by_me',
  created_by_me: '/tasks?status=open&assignment=created_by_me',
  observed_by_me: '/tasks?status=open&assignment=observed_by_me',
}

/** The "da validare" chip target, only ever shown on `assigned_by_me` (D-5). */
export const DASHBOARD_TASK_TO_VALIDATE_HREF = '/tasks?status=in_validation&assignment=assigned_by_me'
