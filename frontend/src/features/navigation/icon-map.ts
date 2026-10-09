import {
  Activity,
  Award,
  BookOpen,
  BookUser,
  Briefcase,
  CalendarClock,
  Building2,
  Circle,
  ClipboardList,
  Clock,
  ContactRound,
  CreditCard,
  DatabaseZap,
  FileText,
  FileUp,
  Files,
  Gift,
  Handshake,
  Layers,
  LayoutDashboard,
  ListChecks,
  ListTree,
  MapPin,
  Megaphone,
  Package,
  Plug,
  Percent,
  Puzzle,
  Ruler,
  ShieldCheck,
  SlidersHorizontal,
  Tag,
  Tags,
  UserPlus,
  Users,
  Waypoints,
  Workflow,
  CircleDot,
  Flag,
  Folder,
  Star,
  Shapes,
  LayoutTemplate,
  Mail,
  FolderArchive,
  ChartColumn,
  Landmark,
  ReceiptEuro,
  type LucideIcon,
} from 'lucide-react'

/**
 * Maps the backend's icon names (navigation config) to Lucide components.
 * Unknown or missing names fall back to a neutral icon so the menu never breaks.
 */
const iconMap: Record<string, LucideIcon> = {
  'layout-dashboard': LayoutDashboard,
  award: Award,
  users: Users,
  'shield-check': ShieldCheck,
  briefcase: Briefcase,
  building: Building2,
  'map-pin': MapPin,
  'building-2': Building2,
  layers: Layers,
  megaphone: Megaphone,
  'database-zap': DatabaseZap,
  'file-text': FileText,
  'file-up': FileUp,
  files: Files,
  gift: Gift,
  handshake: Handshake,
  'contact-round': ContactRound,
  'credit-card': CreditCard,
  'book-user': BookUser,
  tag: Tag,
  tags: Tags,
  waypoints: Waypoints,
  'sliders-horizontal': SlidersHorizontal,
  'list-checks': ListChecks,
  'list-tree': ListTree,
  package: Package,
  percent: Percent,
  puzzle: Puzzle,
  ruler: Ruler,
  'user-plus': UserPlus,
  'clipboard-list': ClipboardList,
  clock: Clock,
  workflow: Workflow,
  // Spec 0101 (tasks group): without these four the Task module and three of
  // its five configurators fell through to the neutral Circle fallback — the
  // menu did not break, it just showed a placeholder, which is why it went
  // unnoticed until visual review.
  'circle-dot': CircleDot,
  flag: Flag,
  folder: Folder,
  star: Star,
  // Spec 0143 rev 2 (AC-018): 'product-typologies' and 'task-templates' fell
  // through to the neutral Circle fallback, in the sidebar too.
  shapes: Shapes,
  'layout-template': LayoutTemplate,
  // Spec 0175: "Modelli email" / "Modelli documenti" under Configurazione.
  mail: Mail,
  'folder-archive': FolderArchive,
  'chart-column': ChartColumn,
  // Spec 0187: "Stato del sistema" under Amministrazione.
  activity: Activity,
  // Spec 0189: "Gestione Conti" under Contabilita'.
  landmark: Landmark,
  'receipt-euro': ReceiptEuro,
  // Spec 0197: "Scadenze" under Contabilita' > Attiva.
  'calendar-clock': CalendarClock,
  // Spec 0209: "API e integrazioni" under Amministrazione.
  plug: Plug,
  // "Documentazione API" under Develop.
  'book-open': BookOpen,
}

export function resolveIcon(name: string | null): LucideIcon {
  if (!name) {
    return Circle
  }
  return iconMap[name] ?? Circle
}
