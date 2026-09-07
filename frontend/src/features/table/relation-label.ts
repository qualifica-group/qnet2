/** A hydrated relation carries either a `name` (most) or a composed `label` (operational sites). */
interface RelationLike {
  name?: string | null
  label?: string | null
}

/**
 * The display label of a hydrated relation value, `null` when it carries none.
 * Its own module (not `rich-cells.tsx`) because a domain cell rendering the
 * same label with its own affordances around it must be able to import it
 * without turning that component file into a mixed-export one.
 */
export function relationLabel(value: unknown): string | null {
  const relation = value as RelationLike | null | undefined
  const label = relation?.name ?? relation?.label
  return typeof label === 'string' && label !== '' ? label : null
}
