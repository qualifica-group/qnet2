import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import type { GeoScope } from '@/features/geo/geo-scope'
import {
  DEFAULT_SITE_TYPE,
  SITE_TYPE_LABEL_KEYS,
} from '@/features/personal-data/address-site-type'
import { nextDraftKey } from '@/features/personal-data/drafts'
import { SITE_TYPES, type AddressDraft, type SiteType } from '@/features/personal-data/types'

/** The "nothing typed yet" baseline: every field blank, no `_key` allocated. */
const BLANK_ADDRESS: AddressDraft = {
  _key: '',
  line1: '',
  line2: null,
  postal_code: null,
  city_id: null,
  province_id: null,
  state_id: null,
  country_id: null,
  is_primary: true,
  site_type: DEFAULT_SITE_TYPE,
}

/** The cascade levels marked required when the caller enforces the city rule. */
const CITY_REQUIRED_LEVELS: ReadonlyArray<GeoScope> = ['city']

/** The geo cascade of a not-yet-started address, before anything is buffered. */
const BLANK_GEO: GeoValue = {
  country_id: null,
  state_id: null,
  province_id: null,
  city_id: null,
}

/**
 * True once any field carries user input — drives the required-once-started rule.
 * The country/state/province levels are deliberately NOT part of the signal:
 * `GeoSelect` preselects the configured national default country on a pristine
 * cascade (`DEFAULT_COUNTRY_ISO2`), so counting them would mark an address
 * nobody touched as started and raise "address required" on sight.
 */
function isStarted(draft: AddressDraft): boolean {
  return Boolean(draft.line1 || draft.line2 || draft.postal_code || draft.city_id)
}

interface AddressCreateFieldProps {
  /** The buffered single address, or an empty array (not started yet). */
  value: AddressDraft[]
  /** Emits the next buffer: `[]` once every field is blank, else `[draft]`. */
  onChange: (next: AddressDraft[]) => void
  /**
   * Renders the "site type" select (spec 0020). Opt-in, default `false`,
   * forwarded verbatim from `AddressesManager`.
   */
  showSiteType?: boolean
  /**
   * Whether a started address must also carry a city. Default `true` (the
   * create flow's rule: an inline address is only useful once geo-located).
   * Callers editing an ALREADY PERSISTED address pass `false`, mirroring the
   * backend, which keeps the city optional on update so a legacy address
   * whose city was never captured stays saveable.
   */
  cityRequired?: boolean
}

/**
 * Single inline, fully-controlled address form for the quick-create flow: no
 * list, no dialog, no "Add" button — an owner has at most one address here and
 * it starts blank (optional). Writes the buffer directly on every change: an
 * address with every field empty clears it, any input creates/updates the sole
 * draft. Once started, `line1` and the city are required (validated inline).
 *
 * The required markers follow that same rule instead of standing there
 * permanently (user directive 2026-09-07): on a pristine address the whole
 * block is optional, so an asterisk on `line1`/city would promise an
 * obligation that does not exist. They appear the moment the address is
 * started — which is also the moment the two fields really become mandatory.
 *
 * Extracted from `AddressesManager` to keep it within the file size limits.
 */
export function AddressCreateField({
  value,
  onChange,
  showSiteType = false,
  cityRequired = true,
}: AddressCreateFieldProps) {
  const { t } = useTranslation()

  // Geo levels chosen (or defaulted by `GeoSelect`) while the address is still
  // unstarted: they live here and NOT in the parent buffer, which must stay
  // empty so an untouched address is neither validated nor submitted. Keeping
  // them is also what stops the national-default effect from re-firing: the
  // cascade it reads is no longer pristine after the first seed.
  const [pendingGeo, setPendingGeo] = useState<GeoValue>(BLANK_GEO)
  const fields = value[0] ?? { ...BLANK_ADDRESS, ...pendingGeo }

  const commit = (next: AddressDraft) => {
    if (!isStarted(next)) {
      setPendingGeo({
        country_id: next.country_id,
        state_id: next.state_id,
        province_id: next.province_id,
        city_id: next.city_id,
      })
      onChange([])
      return
    }
    onChange([{ ...next, _key: next._key || nextDraftKey(), is_primary: true }])
  }

  const geoValue: GeoValue = {
    country_id: fields.country_id,
    state_id: fields.state_id,
    province_id: fields.province_id,
    city_id: fields.city_id,
  }

  const started = isStarted(fields)
  const line1Error = started && !fields.line1 ? t('personalData.addresses.line1Required') : null
  const cityError =
    cityRequired && started && fields.city_id == null ? t('personalData.addresses.cityRequired') : null

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-1.5">
        <label htmlFor="address-create-line1" className="text-sm font-medium">
          {t('personalData.addresses.line1')}
          {started && (
            <span className="ml-1 text-destructive" aria-hidden="true">
              *
            </span>
          )}
        </label>
        <Input
          id="address-create-line1"
          autoComplete="address-line1"
          value={fields.line1}
          onChange={(event) => commit({ ...fields, line1: event.target.value })}
          aria-required={started}
          aria-invalid={line1Error !== null}
          aria-describedby={line1Error ? 'address-create-line1-error' : undefined}
        />
        {line1Error && (
          <span id="address-create-line1-error" role="alert" className="text-sm text-destructive">
            {line1Error}
          </span>
        )}
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="address-create-line2" className="text-sm font-medium">
          {t('personalData.addresses.line2')}
        </label>
        <Input
          id="address-create-line2"
          autoComplete="address-line2"
          value={fields.line2 ?? ''}
          onChange={(event) => commit({ ...fields, line2: event.target.value || null })}
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="address-create-postal-code" className="text-sm font-medium">
          {t('personalData.addresses.postalCode')}
        </label>
        <Input
          id="address-create-postal-code"
          autoComplete="postal-code"
          value={fields.postal_code ?? ''}
          onChange={(event) => commit({ ...fields, postal_code: event.target.value || null })}
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <GeoSelect
          value={geoValue}
          onChange={(next) => commit({ ...fields, ...next })}
          requiredLevels={cityRequired && started ? CITY_REQUIRED_LEVELS : undefined}
        />
        {cityError && (
          <span role="alert" className="text-sm text-destructive">
            {cityError}
          </span>
        )}
      </div>

      {showSiteType && (
        <div className="flex flex-col gap-1.5">
          <label htmlFor="address-create-site-type" className="text-sm font-medium">
            {t('personalData.addresses.siteType')}
          </label>
          <Select
            value={fields.site_type ?? DEFAULT_SITE_TYPE}
            onValueChange={(next) => commit({ ...fields, site_type: next as SiteType })}
          >
            <SelectTrigger id="address-create-site-type" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {SITE_TYPES.map((siteType) => (
                <SelectItem key={siteType} value={siteType}>
                  {t(SITE_TYPE_LABEL_KEYS[siteType])}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}
    </div>
  )
}
