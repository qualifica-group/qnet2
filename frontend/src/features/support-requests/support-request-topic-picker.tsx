import { useId } from 'react'
import type { ControllerRenderProps } from 'react-hook-form'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { cn } from '@/lib/utils'
import type { SupportRequestFormValues } from '@/features/support-requests/support-request-schema'
import type { SupportRequestTopic } from '@/features/support-requests/types'

interface SupportRequestTopicPickerProps {
  topics: SupportRequestTopic[]
  legend: string
  field: ControllerRenderProps<SupportRequestFormValues, 'support_topic_id'>
  error: string | undefined
}

/**
 * Problem types as a grid of large-but-compact selectable cards. Native radio
 * inputs (visually hidden) give keyboard arrows and form semantics for free;
 * the card is the label, so the whole surface is the tap target.
 */
export function SupportRequestTopicPicker({ topics, legend, field, error }: SupportRequestTopicPickerProps) {
  const errorId = useId()

  return (
    <fieldset className="grid gap-2" aria-describedby={error ? errorId : undefined}>
      <legend className="mb-1 text-sm font-medium">{legend}</legend>
      <div className="grid gap-2 sm:grid-cols-2">
        {topics.map((topic) => (
          <label
            key={topic.id}
            className={cn(
              'flex min-h-14 cursor-pointer items-start gap-2 rounded-lg border border-input bg-card p-3 text-left shadow-sm transition-colors hover:bg-accent/40',
              'has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
              field.value === topic.id && 'border-primary bg-accent/40 ring-1 ring-primary',
            )}
          >
            <input
              type="radio"
              name={field.name}
              className="sr-only"
              checked={field.value === topic.id}
              onChange={() => field.onChange(topic.id)}
              onBlur={field.onBlur}
              aria-invalid={error ? true : undefined}
            />
            <DynamicIcon name={topic.icon} className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <span className="min-w-0">
              <span className="block text-sm font-medium">{topic.name}</span>
              {topic.description ? (
                <span className="block text-xs text-muted-foreground">{topic.description}</span>
              ) : null}
            </span>
          </label>
        ))}
      </div>
      {error ? (
        <span id={errorId} role="alert" className="text-xs text-destructive">
          {error}
        </span>
      ) : null}
    </fieldset>
  )
}
