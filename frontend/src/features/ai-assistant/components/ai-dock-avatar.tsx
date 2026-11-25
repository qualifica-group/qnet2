import { Sparkles } from 'lucide-react'
import { cn } from '@/lib/utils'

/** The assistant's round mark in the chat dock, sized like the `UserAvatar` it sits next to. */
export function AiDockAvatar({ className }: { className?: string }) {
  return (
    <span className={cn('flex shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground', className)}>
      <Sparkles className="size-[60%]" aria-hidden="true" />
    </span>
  )
}
