import * as React from "react"
import { Input as InputPrimitive } from "@base-ui/react/input"
import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <InputPrimitive
      type={type}
      data-slot="input"
      className={cn(
        "h-12 w-full min-w-0 rounded-10 border border-rule bg-surface-1 px-3.5 text-body-l text-ink transition-[border-color] duration-(--dur-press) ease-out outline-none file:inline-flex file:h-6 file:border-0 file:bg-transparent file:text-label-14 file:text-ink placeholder:text-ink-2 focus-visible:border-ink-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid focus-visible:outline-ring disabled:cursor-not-allowed disabled:text-ink-3 aria-invalid:border-signal",
        className
      )}
      {...props}
    />
  )
}

export { Input }
