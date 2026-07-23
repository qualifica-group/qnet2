import {
  BookUser,
  Briefcase,
  Building2,
  Circle,
  ClipboardList,
  ContactRound,
  DatabaseZap,
  FileUp,
  Gift,
  Handshake,
  Layers,
  LayoutDashboard,
  ListTree,
  MapPin,
  Megaphone,
  Package,
  Percent,
  Puzzle,
  ShieldCheck,
  SlidersHorizontal,
  Tag,
  Tags,
  UserPlus,
  Users,
  Waypoints,
  Workflow,
  type LucideIcon,
} from 'lucide-react'

/**
 * Maps the backend's icon names (navigation config) to Lucide components.
 * Unknown or missing names fall back to a neutral icon so the menu never breaks.
 */
const iconMap: Record<string, LucideIcon> = {
  'layout-dashboard': LayoutDashboard,
  users: Users,
  'shield-check': ShieldCheck,
  briefcase: Briefcase,
  building: Building2,
  'map-pin': MapPin,
  'building-2': Building2,
  layers: Layers,
  megaphone: Megaphone,
  'database-zap': DatabaseZap,
  'file-up': FileUp,
  gift: Gift,
  handshake: Handshake,
  'contact-round': ContactRound,
  'book-user': BookUser,
  tag: Tag,
  tags: Tags,
  waypoints: Waypoints,
  'sliders-horizontal': SlidersHorizontal,
  'list-tree': ListTree,
  package: Package,
  percent: Percent,
  puzzle: Puzzle,
  'user-plus': UserPlus,
  'clipboard-list': ClipboardList,
  workflow: Workflow,
}

export function resolveIcon(name: string | null): LucideIcon {
  if (!name) {
    return Circle
  }
  return iconMap[name] ?? Circle
}
