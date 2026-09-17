import {
  Building2,
  Globe,
  Mail,
  Phone,
  Printer,
  ShieldCheck,
  Tag,
  User,
  type LucideIcon,
} from 'lucide-react'

/**
 * Enum icon token (kebab-case, as emitted by the backend `#[Icon]` attribute) →
 * Lucide component. Backend tokens are kebab-case while lucide exports
 * PascalCase, so this explicit map is the translation layer (kept static so it
 * stays tree-shakeable and type-checked). Mirrors `action-icon-map.ts`.
 */
export type EnumIconMap = Record<string, LucideIcon>

export const defaultEnumIconMap: EnumIconMap = {
  // user_type badge
  user: User,
  building: Building2,
  // contact types (ContactTypeEnum)
  phone: Phone,
  printer: Printer,
  mail: Mail,
  'shield-check': ShieldCheck,
  globe: Globe,
}

/** Neutral fallback so an unknown (but present) token never breaks a cell. */
export const fallbackEnumIcon: LucideIcon = Tag

/**
 * Resolve a backend icon token to a Lucide component. Returns `undefined` when
 * the token is absent/empty (so callers can render no icon), and the neutral
 * fallback when the token is present but unmapped.
 */
export function resolveEnumIcon(
  name: string | null | undefined,
  overrides?: EnumIconMap,
): LucideIcon | undefined {
  if (!name) {
    return undefined
  }
  return overrides?.[name] ?? defaultEnumIconMap[name] ?? fallbackEnumIcon
}
