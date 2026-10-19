import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { formatOnBlur } from '@/lib/formatting/format-on-blur'
import { formatContactValue } from '@/lib/formatting/input-format'
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
import { useEnumOptions } from '@/features/config/use-config'
import {
  buildContactSchema,
  type ContactFormValues,
} from '@/features/personal-data/contact-schema'
import type { ContactDraft } from '@/features/personal-data/types'

interface ContactFormProps {
  /** When present the form edits this draft; otherwise it creates a new one. */
  contact?: ContactDraft
  /** Returns the validated draft fields to the manager. */
  onSubmit: (fields: Omit<ContactDraft, '_key'>) => void
  /** Called when the user cancels. */
  onCancel: () => void
  /** True while an immediate write is in flight: disables the actions. */
  submitting?: boolean
  /** Shows the "work" checkbox (spec 0272 D-7): only the user's form does, other owners leave `is_work` untouched. */
  showWorkFlag?: boolean
}

/**
 * Create/edit form for a single contact channel, rendered inside the manager's
 * dialog. Validates per-type (mirroring the backend) and hands the values back
 * through `onSubmit`; the manager decides whether to buffer them or persist them
 * immediately. Owner-agnostic.
 */
export function ContactForm({ contact, onSubmit, onCancel, submitting = false, showWorkFlag = false }: ContactFormProps) {
  const { t } = useTranslation()
  const typeOptions = useEnumOptions('contact_type')
  const schema = useMemo(() => buildContactSchema(t), [t])

  const defaultType =
    contact?.type ??
    typeOptions.find((option) => option.is_default)?.value ??
    typeOptions[0]?.value ??
    ''

  const form = useForm<ContactFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      type: defaultType,
      value: contact?.value ?? '',
      label: contact?.label ?? '',
      is_primary: contact?.is_primary ?? false,
      is_work: contact?.is_work ?? false,
    },
  })

  const handleSubmit = (values: ContactFormValues) => {
    onSubmit({
      ...(contact?.id !== undefined ? { id: contact.id } : {}),
      type: values.type,
      value: values.value,
      label: values.label || null,
      is_primary: values.is_primary,
      ...(showWorkFlag ? { is_work: values.is_work } : contact?.is_work !== undefined ? { is_work: contact.is_work } : {}),
    })
  }

  return (
    <Form {...form}>
      {/* A div, not a form: the dialog is portaled outside the outer user form,
          but keeping a plain button (RHF's handleSubmit) avoids any nested-form
          ambiguity and works identically for the buffered and immediate paths. */}
      <div className="flex flex-col gap-3">
        <FormField
          control={form.control}
          name="type"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('personalData.contacts.type')}</FormLabel>
              <Select value={field.value} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {typeOptions.map((option) => (
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

        <FormField
          control={form.control}
          name="value"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('personalData.contacts.value')}</FormLabel>
              <FormControl>
                <Input
                  autoComplete="off"
                  {...field}
                  onBlur={formatOnBlur(field, (value) =>
                    formatContactValue(form.getValues('type'), value),
                  )}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="label"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('personalData.contacts.label')}</FormLabel>
              <FormControl>
                <Input autoComplete="off" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="is_primary"
          render={({ field }) => (
            <FormItem>
              <label className="flex items-center gap-2 text-sm font-normal">
                <input
                  type="checkbox"
                  className="size-4 accent-primary"
                  checked={field.value}
                  onChange={(event) => field.onChange(event.target.checked)}
                />
                {t('personalData.contacts.primary')}
              </label>
              <FormMessage />
            </FormItem>
          )}
        />

        {showWorkFlag ? (
          <FormField
            control={form.control}
            name="is_work"
            render={({ field }) => (
              <FormItem>
                <label className="flex items-center gap-2 text-sm font-normal">
                  <input
                    type="checkbox"
                    className="size-4 accent-primary"
                    checked={field.value ?? false}
                    onChange={(event) => field.onChange(event.target.checked)}
                  />
                  {t('personalData.contacts.work')}
                </label>
                <FormMessage />
              </FormItem>
            )}
          />
        ) : null}

        <div className="flex justify-end gap-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onCancel}
            disabled={submitting}
          >
            {t('personalData.contacts.cancel')}
          </Button>
          <Button
            type="button"
            size="sm"
            onClick={form.handleSubmit(handleSubmit)}
            disabled={submitting}
          >
            {submitting
              ? t('personalData.contacts.saving')
              : t('personalData.contacts.save')}
          </Button>
        </div>
      </div>
    </Form>
  )
}
