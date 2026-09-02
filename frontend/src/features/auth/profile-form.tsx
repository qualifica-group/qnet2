import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
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
import { updateProfile } from '@/features/auth/api'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'
import { PersonalDataSection } from '@/features/personal-data/personal-data-section'
import {
  cardToDraft,
  draftToPayload,
  emptyPersonalDataDraft,
} from '@/features/personal-data/drafts'
import {
  describeCardIssues,
  isPersonalDataCardValid,
} from '@/features/personal-data/personal-data-issues'
import type { PersonalDataDraft } from '@/features/personal-data/types'

interface ProfileValues {
  locale: string
}

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['locale'] as const

/**
 * Self-service profile form for the authenticated user. Mirrors the Users module
 * (features/users/user-form.tsx): locale is the only RHF/Zod field, while the
 * registry card + contacts + addresses are edited through the shared, owner-
 * agnostic PersonalDataSection held in a parent-owned buffer and submitted inside
 * the single PATCH /auth/me payload (ADR 0013). There is no free `name` field —
 * the display name is derived server-side from the card. The registration email
 * is shown read-only and is never part of the editable form state or payload.
 */
export function ProfileForm() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  // Bumped on every refused save so the buffered card marks its own missing
  // fields (it takes no part in this form's submit — see PersonalDataCardForm).
  const [revalidateSignal, setRevalidateSignal] = useState(0)
  // Selectable locales come from the backend bootstrap config (GET /api/config),
  // never hardcoded on the frontend.
  const localeOptions = useEnumOptions('locale')
  const defaultLocale =
    localeOptions.find((option) => option.is_default)?.value ??
    localeOptions[0]?.value ??
    'en'

  // The buffered personal-data tree, owned here. Seeded once from the loaded
  // user's card (me.personal_data) — present means an existing card to upsert,
  // null means a blank, always-active card (parity with the Users module).
  const [draft, setDraft] = useState<PersonalDataDraft>(() =>
    user?.personal_data
      ? cardToDraft(user.personal_data)
      : emptyPersonalDataDraft(),
  )

  const schema = useMemo(
    () =>
      z.object({
        locale: z.string().min(1),
      }),
    [],
  )

  const form = useForm<ProfileValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      locale: user?.locale ?? defaultLocale,
    },
  })

  const onSubmit = async (values: ProfileValues) => {
    setServerError(null)

    // The registry card is mandatory: block the save until the required identity
    // fields (name + surname, or company name) are valid, naming what is missing
    // and asking the card to mark those fields inline.
    if (!isPersonalDataCardValid(draft, t)) {
      setServerError(
        t('personalData.section.incomplete', {
          fields: describeCardIssues(draft, t).join(' · '),
        }),
      )
      setRevalidateSignal((signal) => signal + 1)
      return
    }

    try {
      const updatedUser = await updateProfile({
        locale: values.locale,
        personal_data: draftToPayload(draft),
      })
      // Updating the `me` cache triggers the AuthProvider effect that applies
      // the user's locale, so the UI language switches automatically here.
      queryClient.setQueryData(authKeys.me, updatedUser)
      toast.success(t('settings.profileUpdated'))
    } catch (error) {
      if (
        !applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])
      ) {
        setServerError(t('settings.genericError'))
      }
    }
  }

  // The authenticated user is loaded by the AuthProvider before the settings
  // page renders; show a skeleton mirroring the form shape on the rare miss.
  if (!user) {
    return (
      <div className="flex flex-col gap-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <div className="flex flex-col gap-3 border-t pt-4">
          <Skeleton className="h-5 w-32" />
          <div className="grid grid-cols-2 gap-3">
            <Skeleton className="h-9" />
            <Skeleton className="h-9" />
          </div>
          <Skeleton className="h-9" />
        </div>
        <Skeleton className="h-9 w-32" />
      </div>
    )
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
        <div className="grid gap-2">
          <Label htmlFor="profile-email">{t('settings.email')}</Label>
          <Input
            id="profile-email"
            type="email"
            autoComplete="email"
            value={user.email}
            readOnly
            disabled
          />
        </div>

        <FormField
          control={form.control}
          name="locale"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('settings.language')}</FormLabel>
              <Select value={field.value} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {localeOptions.map((option) => (
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

        <div className="border-t pt-4">
          <PersonalDataSection
            value={draft}
            onChange={setDraft}
            revalidateSignal={revalidateSignal}
          />
        </div>

        {serverError && (
          <p className="text-sm font-medium text-destructive" role="alert">
            {serverError}
          </p>
        )}

        <Button type="submit" disabled={form.formState.isSubmitting}>
          {form.formState.isSubmitting
            ? t('settings.savingProfile')
            : t('settings.saveProfile')}
        </Button>
      </form>
    </Form>
  )
}
