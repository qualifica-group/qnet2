import {
  AlignLeft, Calendar, CalendarClock, Clock, Hash, Link, List, Mail, Palette,
  Sigma, Table2, ToggleRight, Type, Waypoints, type LucideIcon,
} from 'lucide-react'
import type { CustomFieldType } from '@/features/custom-fields/types'

/**
 * Glyph shown per {@link CustomFieldType}: the definition form's type picker
 * (custom fields AND attributes, spec 0017/0021 — same 13-type catalogue),
 * plus every read-only badge that names a field's type (attribute-assignment
 * editor, category detail). One map, reused everywhere a type needs a glyph.
 */
export const FIELD_TYPE_ICONS: Record<CustomFieldType, LucideIcon> = {
  text: Type,
  textarea: AlignLeft,
  integer: Hash,
  decimal: Sigma,
  boolean: ToggleRight,
  enum: List,
  relation: Waypoints,
  date: Calendar,
  datetime: CalendarClock,
  time: Clock,
  email: Mail,
  url: Link,
  color: Palette,
  table: Table2,
}
