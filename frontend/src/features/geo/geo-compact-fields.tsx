import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { GeoField, type GeoFieldProps } from '@/features/geo/geo-field'

/** Separator of the derived ancestor line, matching the address list summary. */
const SUMMARY_SEPARATOR = ' · '

interface GeoCompactFieldsProps {
  country: GeoFieldProps
  state: GeoFieldProps
  province: GeoFieldProps
  city: GeoFieldProps
  /**
   * False when comune-first cannot drive the cascade (a linked parent entity
   * locks a level, spec 0027 BR-5): the three ancestors then stay open, since
   * nothing would fill them in.
   */
  collapsible: boolean
}

/** The chosen option's name at one level, or null when nothing is picked yet. */
function pickedName(field: GeoFieldProps): string | null {
  if (field.value === null) {
    return null
  }
  return field.options.find((option) => option.id === field.value)?.name ?? null
}

/**
 * Comune-first layout of the geo cascade: the comune is the single primary
 * control (it is searchable on its own and backfills its whole ancestor chain,
 * see `GeoSelect`), while nazione/regione/provincia collapse into one derived
 * summary line — the shape used by mainstream CRMs, and the reason an address
 * block is four fields shorter than the top-down cascade.
 *
 * The ancestors stay REACHABLE, never removed: the disclosure reopens them for
 * the cases the comune cannot serve — a foreign address, a country with no
 * province level, an address known only down to the region (user decision
 * 2026-09-07, "lasciarli aperti e' cio' che tiene raggiungibile un indirizzo
 * estero").
 */
export function GeoCompactFields({
  country,
  state,
  province,
  city,
  collapsible,
}: GeoCompactFieldsProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  const ancestors = (
    <div className="grid gap-3 sm:grid-cols-3">
      <GeoField {...country} />
      <GeoField {...state} />
      <GeoField {...province} />
    </div>
  )

  if (!collapsible) {
    return (
      <div className="flex flex-col gap-3">
        {ancestors}
        <GeoField {...city} />
      </div>
    )
  }

  // Finest level first, the city excluded: it is the primary control right above.
  const summary = [pickedName(province), pickedName(state), pickedName(country)]
    .filter(Boolean)
    .join(SUMMARY_SEPARATOR)

  return (
    <Collapsible open={open} onOpenChange={setOpen} className="flex flex-col gap-1.5">
      <GeoField {...city} />
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
        {summary !== '' && (
          <span className="min-w-0 truncate text-xs text-muted-foreground">{summary}</span>
        )}
        <CollapsibleTrigger asChild>
          <button
            type="button"
            className="group flex items-center gap-1 rounded-md text-xs font-medium text-muted-foreground underline-offset-2 transition-colors hover:text-foreground hover:underline outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50"
          >
            {open ? t('geo.hideArea') : t('geo.editArea')}
            <ChevronDown
              className="size-3.5 transition-transform motion-safe:duration-200 group-data-[state=open]:rotate-180"
              aria-hidden="true"
            />
          </button>
        </CollapsibleTrigger>
      </div>
      <CollapsibleContent className="pt-1.5">{ancestors}</CollapsibleContent>
    </Collapsible>
  )
}
