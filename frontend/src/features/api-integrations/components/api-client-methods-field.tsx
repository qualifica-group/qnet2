import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Checkbox } from '@/components/ui/checkbox'
import { FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { API_CLIENT_METHODS, type ApiClientFormValues } from '@/features/api-integrations/api-client-schema'
import type { ApiClientMethod } from '@/features/api-integrations/types'

/** PUT governs PATCH too, so the chip says both. */
const METHOD_LABELS: Record<ApiClientMethod, string> = {
  GET: 'GET',
  POST: 'POST',
  PUT: 'PUT / PATCH',
  DELETE: 'DELETE',
}

interface ApiClientMethodsFieldProps {
  control: Control<ApiClientFormValues>
}

/** The HTTP methods the client may use (spec 0392): one compact checkbox chip each, at least one. */
export function ApiClientMethodsField({ control }: ApiClientMethodsFieldProps) {
  const { t } = useTranslation()

  return (
    <FormField
      control={control}
      name="allowed_methods"
      render={({ field }) => {
        const toggle = (method: ApiClientMethod, checked: boolean) =>
          field.onChange(
            API_CLIENT_METHODS.filter((candidate) =>
              candidate === method ? checked : field.value.includes(candidate),
            ),
          )

        return (
          <FormItem>
            <FormLabel id="api-client-methods-label">{t('apiIntegrations.form.allowedMethods')}</FormLabel>
            <div role="group" aria-labelledby="api-client-methods-label" className="flex flex-wrap gap-2">
              {API_CLIENT_METHODS.map((method) => (
                <label
                  key={method}
                  className="flex cursor-pointer items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1 font-mono text-xs"
                >
                  <Checkbox
                    checked={field.value.includes(method)}
                    onCheckedChange={(checked) => toggle(method, checked === true)}
                  />
                  {METHOD_LABELS[method]}
                </label>
              ))}
            </div>
            <FormDescription className="text-xs">{t('apiIntegrations.form.allowedMethodsHint')}</FormDescription>
            <FormMessage />
          </FormItem>
        )
      }}
    />
  )
}
