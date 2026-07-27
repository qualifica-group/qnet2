import { useEffect, useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { BirthCityField } from '@/features/personal-data/birth-city-field'
import { formatOnBlur } from '@/lib/formatting/format-on-blur'
import { formatIdentityField } from '@/lib/formatting/input-format'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useEnumOptions } from '@/features/config/use-config'
import {
  buildPersonalDataSchema,
  type PersonalDataFormValues,
} from '@/features/personal-data/personal-data-schema'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
  PersonalDataType,
} from '@/features/personal-data/types'

interface PersonalDataCardFormProps {
  /** The buffered card fields owned by the parent section. */
  value: PersonalDataDraft
  /** Emits the next card draft (fields only; children are managed separately). */
  onChange: (next: PersonalDataDraft) => void
  /**
   * Resolves gating for each card field by its `personal_data.*` key (spec
   * 0008). Optional: omitting it keeps today's ungated behaviour (self-service
   * profile, AC-013).
   */
  fieldPermission?: PersonalDataFieldPermissionResolver
  /**
   * Locks the card to a single `type` (e.g. Company Sites are always a
   * `company`): the individual/company toggle is hidden and every emitted
   * draft carries this type. Omitting it keeps today's selectable behaviour
   * (registries/self-service profile).
   */
  lockType?: PersonalDataType
}

interface FieldGate {
  visible: boolean
  disabled: boolean
  readOnly: boolean
  required: boolean
}

/**
 * Resolves one field's render gating. Without a resolver, every field stays
 * visible/editable and `required` falls back to this card's own (schema-driven)
 * default, matching today's behaviour exactly.
 */
function resolveGate(
  fieldPermission: PersonalDataFieldPermissionResolver | undefined,
  key: string,
  fallbackRequired: boolean,
): FieldGate {
  if (!fieldPermission) {
    return { visible: true, disabled: false, readOnly: false, required: fallbackRequired }
  }
  const permission = fieldPermission(key)
  return {
    visible: permission.visible,
    disabled: permission.disabled || !permission.editable,
    readOnly: permission.readonly,
    required: permission.required,
  }
}

/**
 * Controlled/buffered create/edit form for the registry card fields. The `type`
 * options come from the server config; individual vs company fields
 * toggle by the selected type, mirroring the backend per-type contract. It keeps
 * RHF + Zod for inline validation but performs no network call: every change is
 * lifted into the parent buffer through `onChange`, and the whole tree is saved by
 * the surrounding user form (ADR 0012).
 */
export function PersonalDataCardForm({
  value,
  onChange,
  fieldPermission,
  lockType,
}: PersonalDataCardFormProps) {
  const { t } = useTranslation()
  const typeOptions = useEnumOptions('personal_data_type')
  const genderOptions = useEnumOptions('gender')
  const schema = useMemo(() => buildPersonalDataSchema(t), [t])

  const form = useForm<PersonalDataFormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      type: lockType ?? value.type,
      first_name: value.first_name ?? '',
      last_name: value.last_name ?? '',
      company_name: value.company_name ?? '',
      tax_code: value.tax_code ?? '',
      vat_number: value.vat_number ?? '',
      sdi_code: value.sdi_code ?? '',
      birth_date: value.birth_date ?? '',
      birth_city_id: value.birth_city_id ?? null,
      // Individual cards always carry a gender (default male); company: none.
      gender: value.gender ?? 'male',
    },
  })

  // Watch every field so edits flow into the parent buffer. `useWatch` re-renders
  // this component on each change; `isCompany` toggles which fields render.
  const watched = useWatch({ control: form.control })
  const isCompany = (lockType ?? watched.type) === 'company'

  const typeGate = resolveGate(fieldPermission, 'personal_data.type', true)
  const companyNameGate = resolveGate(fieldPermission, 'personal_data.company_name', true)
  const firstNameGate = resolveGate(fieldPermission, 'personal_data.first_name', true)
  const lastNameGate = resolveGate(fieldPermission, 'personal_data.last_name', true)
  const taxCodeGate = resolveGate(fieldPermission, 'personal_data.tax_code', false)
  const vatNumberGate = resolveGate(fieldPermission, 'personal_data.vat_number', false)
  const sdiCodeGate = resolveGate(fieldPermission, 'personal_data.sdi_code', false)
  const birthDateGate = resolveGate(fieldPermission, 'personal_data.birth_date', false)
  const birthCityGate = resolveGate(fieldPermission, 'personal_data.birth_city_id', false)
  const genderGate = resolveGate(fieldPermission, 'personal_data.gender', false)

  // Mirror the current field values into the parent buffer (in an effect, so the
  // parent update happens after this render rather than during it), preserving the
  // draft's id and its children. The no-op compare prevents an update loop.
  const next: PersonalDataDraft = {
    ...(value.id !== undefined ? { id: value.id } : {}),
    type: (lockType ?? watched.type ?? value.type) as PersonalDataDraft['type'],
    first_name: watched.first_name || null,
    last_name: watched.last_name || null,
    company_name: watched.company_name || null,
    tax_code: watched.tax_code || null,
    vat_number: watched.vat_number || null,
    sdi_code: watched.sdi_code || null,
    birth_date: watched.birth_date || null,
    // The comune of birth belongs to an individual, like the gender above.
    birth_city_id: isCompany ? null : (watched.birth_city_id ?? null),
    birth_city: value.birth_city,
    // Gender is an individual-only attribute: a company card carries none.
    gender: isCompany ? null : (watched.gender ?? 'male'),
    contacts: value.contacts,
    addresses: value.addresses,
  }
  const changed = !sameCardFields(next, value)
  useEffect(() => {
    if (changed) {
      onChange(next)
    }
    // `next` is recomputed each render from `watched`; gating on `changed` keeps
    // this to a single parent update per actual field change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [changed])

  return (
    <Form {...form}>
      {/* A div, not a form: this card is buffered and lives inside the user
          form, so it must never nest a <form> nor own a submit. */}
      <div className="flex flex-col gap-3">
        {!lockType && typeGate.visible && (
          <FormField
            control={form.control}
            name="type"
            render={({ field }) => (
              <FormItem>
                <FormLabel required={typeGate.required}>
                  {t('personalData.form.type')}
                </FormLabel>
                {/* Full-width segmented toggle (individual vs company) instead of a
                    select: the choice reshapes the form, so it stays visible. */}
                <Tabs value={field.value} onValueChange={field.onChange}>
                  <TabsList className="w-full">
                    {typeOptions.map((option) => (
                      <TabsTrigger
                        key={option.value}
                        value={option.value}
                        disabled={typeGate.disabled}
                        className="flex-1"
                      >
                        {option.label}
                      </TabsTrigger>
                    ))}
                  </TabsList>
                </Tabs>
                <FormMessage />
              </FormItem>
            )}
          />
        )}

        {isCompany
          ? companyNameGate.visible && (
              <FormField
                control={form.control}
                name="company_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required={companyNameGate.required}>
                      {t('personalData.form.companyName')}
                    </FormLabel>
                    <FormControl>
                      <Input
                        autoComplete="organization"
                        disabled={companyNameGate.disabled}
                        readOnly={companyNameGate.readOnly}
                        {...field}
                        onBlur={formatOnBlur(field, (value) =>
                          formatIdentityField('company_name', value),
                        )}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )
          : (firstNameGate.visible || lastNameGate.visible) && (
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {firstNameGate.visible && (
                  <FormField
                    control={form.control}
                    name="first_name"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel required={firstNameGate.required}>
                          {t('personalData.form.firstName')}
                        </FormLabel>
                        <FormControl>
                          <Input
                            autoComplete="given-name"
                            disabled={firstNameGate.disabled}
                            readOnly={firstNameGate.readOnly}
                            {...field}
                            onBlur={formatOnBlur(field, (value) =>
                              formatIdentityField('first_name', value),
                            )}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                )}
                {lastNameGate.visible && (
                  <FormField
                    control={form.control}
                    name="last_name"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel required={lastNameGate.required}>
                          {t('personalData.form.lastName')}
                        </FormLabel>
                        <FormControl>
                          <Input
                            autoComplete="family-name"
                            disabled={lastNameGate.disabled}
                            readOnly={lastNameGate.readOnly}
                            {...field}
                            onBlur={formatOnBlur(field, (value) =>
                              formatIdentityField('last_name', value),
                            )}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                )}
              </div>
            )}

        {(taxCodeGate.visible || vatNumberGate.visible) && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {taxCodeGate.visible && (
              <FormField
                control={form.control}
                name="tax_code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required={taxCodeGate.required}>
                      {t('personalData.form.taxCode')}
                    </FormLabel>
                    <FormControl>
                      <Input
                        autoComplete="off"
                        disabled={taxCodeGate.disabled}
                        readOnly={taxCodeGate.readOnly}
                        {...field}
                        onBlur={formatOnBlur(field, (value) =>
                          formatIdentityField('tax_code', value, isCompany),
                        )}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}
            {vatNumberGate.visible && (
              <FormField
                control={form.control}
                name="vat_number"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required={vatNumberGate.required}>
                      {t('personalData.form.vatNumber')}
                    </FormLabel>
                    <FormControl>
                      <Input
                        autoComplete="off"
                        disabled={vatNumberGate.disabled}
                        readOnly={vatNumberGate.readOnly}
                        {...field}
                        onBlur={formatOnBlur(field, (value) =>
                          formatIdentityField('vat_number', value),
                        )}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}
          </div>
        )}

        {isCompany && sdiCodeGate.visible && (
          <FormField
            control={form.control}
            name="sdi_code"
            render={({ field }) => (
              <FormItem>
                <FormLabel required={sdiCodeGate.required}>
                  {t('personalData.form.sdiCode')}
                </FormLabel>
                <FormControl>
                  <Input
                    autoComplete="off"
                    disabled={sdiCodeGate.disabled}
                    readOnly={sdiCodeGate.readOnly}
                    {...field}
                    onBlur={formatOnBlur(field, (value) =>
                      formatIdentityField('sdi_code', value),
                    )}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        )}

        {!isCompany && (birthDateGate.visible || genderGate.visible) && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {birthDateGate.visible && (
              <FormField
                control={form.control}
                name="birth_date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required={birthDateGate.required}>
                      {t('personalData.form.birthDate')}
                    </FormLabel>
                    <FormControl>
                      <Input
                        type="date"
                        disabled={birthDateGate.disabled}
                        readOnly={birthDateGate.readOnly}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}
            {genderGate.visible && (
              <FormField
                control={form.control}
                name="gender"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required={genderGate.required}>
                      {t('personalData.form.gender')}
                    </FormLabel>
                    <Select
                      value={field.value}
                      onValueChange={field.onChange}
                      disabled={genderGate.disabled || genderGate.readOnly}
                    >
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {genderOptions.map((option) => (
                          <SelectItem key={option.value} value={option.value}>
                            {option.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}
          </div>
        )}

        {!isCompany && birthCityGate.visible && (
          <FormField
            control={form.control}
            name="birth_city_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel required={birthCityGate.required}>
                  {t('personalData.form.birthCity')}
                </FormLabel>
                <FormControl>
                  <BirthCityField
                    value={field.value ?? null}
                    hydrated={value.birth_city}
                    onChange={field.onChange}
                    disabled={birthCityGate.disabled || birthCityGate.readOnly}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        )}
      </div>
    </Form>
  )
}

/** True when two drafts carry identical card fields (children ignored). */
function sameCardFields(a: PersonalDataDraft, b: PersonalDataDraft): boolean {
  return (
    a.type === b.type &&
    a.first_name === b.first_name &&
    a.last_name === b.last_name &&
    a.company_name === b.company_name &&
    a.tax_code === b.tax_code &&
    a.vat_number === b.vat_number &&
    a.sdi_code === b.sdi_code &&
    a.birth_date === b.birth_date &&
    a.birth_city_id === b.birth_city_id &&
    a.gender === b.gender
  )
}
