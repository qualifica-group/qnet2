import { useMemo } from 'react'
import { useForm, useWatch, type Control } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { GeoSelect } from '@/features/geo/geo-select'
import type { GeoValue } from '@/features/geo/geo-select'
import {
  buildAddressSchema,
  type AddressFormValues,
} from '@/features/personal-data/address-schema'
import {
  DEFAULT_SITE_TYPE,
  SITE_TYPE_LABEL_KEYS,
} from '@/features/personal-data/address-site-type'
import { SITE_TYPES, type AddressDraft, type SiteType } from '@/features/personal-data/types'

/**
 * Bridges the cascading <GeoSelect> to the three geo id fields of the form,
 * subscribing with `useWatch` so the selects re-render on every parent change
 * and writing the (possibly reset) ids back through `setValue`.
 */
function GeoFields({
  control,
  setValue,
  disabled,
}: {
  control: Control<AddressFormValues>
  setValue: (
    name: 'country_id' | 'state_id' | 'province_id' | 'city_id',
    value: number | null,
  ) => void
  disabled: boolean
}) {
  const value: GeoValue = {
    country_id: useWatch({ control, name: 'country_id' }) ?? null,
    state_id: useWatch({ control, name: 'state_id' }) ?? null,
    province_id: useWatch({ control, name: 'province_id' }) ?? null,
    city_id: useWatch({ control, name: 'city_id' }) ?? null,
  }

  return (
    <GeoSelect
      value={value}
      onChange={(next) => {
        setValue('country_id', next.country_id)
        setValue('state_id', next.state_id)
        setValue('province_id', next.province_id)
        setValue('city_id', next.city_id)
      }}
      disabled={disabled}
      layout="compact"
    />
  )
}

interface AddressFormProps {
  /** When present the form edits this draft; otherwise it creates a new one. */
  address?: AddressDraft
  /** Returns the validated draft fields to the manager. */
  onSubmit: (fields: Omit<AddressDraft, '_key'>) => void
  /** Called when the user cancels. */
  onCancel: () => void
  /** True while an immediate write is in flight: disables the actions. */
  submitting?: boolean
  /**
   * Renders the "site type" select (spec 0020). Opt-in, default `false`: every
   * owner but the Registries module keeps this field out of view and the
   * backend default (`billing`) applies untouched.
   */
  showSiteType?: boolean
}

/**
 * Create/edit form for a single address, rendered inside the manager's dialog.
 * Validates (only `line1` required, mirroring the backend) and hands the values
 * back through `onSubmit`; the manager decides whether to buffer them or persist
 * them immediately. Owner-agnostic.
 */
export function AddressForm({
  address,
  onSubmit,
  onCancel,
  submitting = false,
  showSiteType = false,
}: AddressFormProps) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildAddressSchema(t), [t])

  const form = useForm<AddressFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      line1: address?.line1 ?? '',
      line2: address?.line2 ?? '',
      postal_code: address?.postal_code ?? '',
      country_id: address?.country_id ?? null,
      state_id: address?.state_id ?? null,
      province_id: address?.province_id ?? null,
      city_id: address?.city_id ?? null,
      // A new address is the owner's primary one by default (user directive
      // 2026-09-10); editing keeps whatever the address already carries.
      is_primary: address?.is_primary ?? true,
      site_type: address?.site_type ?? DEFAULT_SITE_TYPE,
    },
  })

  const handleSubmit = (values: AddressFormValues) => {
    onSubmit({
      ...(address?.id !== undefined ? { id: address.id } : {}),
      line1: values.line1,
      line2: values.line2 || null,
      postal_code: values.postal_code || null,
      country_id: values.country_id ?? null,
      state_id: values.state_id ?? null,
      province_id: values.province_id ?? null,
      city_id: values.city_id ?? null,
      is_primary: values.is_primary,
      site_type: values.site_type ?? DEFAULT_SITE_TYPE,
    })
  }

  return (
    <Form {...form}>
      {/* A div, not a form: the dialog is portaled outside the outer user form,
          but keeping a plain button (RHF's handleSubmit) avoids any nested-form
          ambiguity and works identically for the buffered and immediate paths. */}
      <div className="flex flex-col gap-3">
        <div className="grid gap-3 sm:grid-cols-3">
          <FormField
            control={form.control}
            name="line1"
            render={({ field }) => (
              <FormItem className="sm:col-span-2">
                <FormLabel required>{t('personalData.addresses.line1')}</FormLabel>
                <FormControl>
                  <Input autoComplete="address-line1" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="postal_code"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('personalData.addresses.postalCode')}</FormLabel>
                <FormControl>
                  <Input autoComplete="postal-code" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="line2"
            render={({ field }) => (
              <FormItem className="sm:col-span-3">
                <FormLabel>{t('personalData.addresses.line2')}</FormLabel>
                <FormControl>
                  <Input autoComplete="address-line2" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <div className="sm:col-span-3">
            <GeoFields
              control={form.control}
              setValue={(name, value) => form.setValue(name, value)}
              disabled={form.formState.isSubmitting}
            />
          </div>
        </div>

        <FormField
          control={form.control}
          name="is_primary"
          render={({ field }) => (
            <FormItem>
              <label className="flex items-center gap-2 text-sm font-normal">
                <Checkbox
                  checked={field.value}
                  onCheckedChange={(checked) => field.onChange(checked === true)}
                />
                {t('personalData.addresses.primary')}
              </label>
              <FormMessage />
            </FormItem>
          )}
        />

        {showSiteType && (
          <FormField
            control={form.control}
            name="site_type"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('personalData.addresses.siteType')}</FormLabel>
                <Select
                  value={field.value ?? DEFAULT_SITE_TYPE}
                  onValueChange={(next) => field.onChange(next as SiteType)}
                >
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {SITE_TYPES.map((siteType) => (
                      <SelectItem key={siteType} value={siteType}>
                        {t(SITE_TYPE_LABEL_KEYS[siteType])}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
        )}

        <div className="flex justify-end gap-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onCancel}
            disabled={submitting}
          >
            {t('personalData.addresses.cancel')}
          </Button>
          <Button
            type="button"
            size="sm"
            onClick={form.handleSubmit(handleSubmit)}
            disabled={submitting}
          >
            {submitting
              ? t('personalData.addresses.saving')
              : t('personalData.addresses.save')}
          </Button>
        </div>
      </div>
    </Form>
  )
}
