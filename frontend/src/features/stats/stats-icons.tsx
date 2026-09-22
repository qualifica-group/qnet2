import type { ReactElement } from 'react'
import {
  AlertTriangle,
  Briefcase,
  Building2,
  CalendarClock,
  CheckCircle2,
  Clock,
  FolderTree,
  Layers,
  MapPin,
  Megaphone,
  Package,
  Percent,
  Target,
  Timer,
  TrendingUp,
  UserCheck,
  UserX,
  Users,
  Wallet,
} from 'lucide-react'

/**
 * Allow-list of the icons a `stat` widget may reference by name (spec 0026
 * contract). The backend sends a name, never a component. Elements are built
 * once at module level: nothing is created during render.
 */
const STATS_ICONS: Record<string, ReactElement> = {
  'alert-triangle': <AlertTriangle aria-hidden="true" />,
  briefcase: <Briefcase aria-hidden="true" />,
  building: <Building2 aria-hidden="true" />,
  'calendar-clock': <CalendarClock aria-hidden="true" />,
  'check-circle': <CheckCircle2 aria-hidden="true" />,
  clock: <Clock aria-hidden="true" />,
  'folder-tree': <FolderTree aria-hidden="true" />,
  layers: <Layers aria-hidden="true" />,
  'map-pin': <MapPin aria-hidden="true" />,
  megaphone: <Megaphone aria-hidden="true" />,
  package: <Package aria-hidden="true" />,
  percent: <Percent aria-hidden="true" />,
  target: <Target aria-hidden="true" />,
  timer: <Timer aria-hidden="true" />,
  'trending-up': <TrendingUp aria-hidden="true" />,
  'user-check': <UserCheck aria-hidden="true" />,
  'user-x': <UserX aria-hidden="true" />,
  users: <Users aria-hidden="true" />,
  wallet: <Wallet aria-hidden="true" />,
}

/** Resolves a widget's icon name to an element; an unknown name renders no icon. */
export function resolveStatsIcon(name: string | null): ReactElement | undefined {
  if (!name) {
    return undefined
  }

  return STATS_ICONS[name]
}
