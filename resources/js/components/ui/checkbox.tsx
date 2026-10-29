import * as React from "react"
import { CheckIcon } from "lucide-react"
import { cn } from "@/lib/utils"

/**
 * Case à cocher du design (page Coups) : carré de 22 px, rayon 6, bordure de
 * 1,5 px `ink-4` décochée (exception de contraste, D9), pleine encre cochée,
 * coche de 14 px qui passe de l'échelle 0,4 à 1. Case native : le clavier, le
 * nom et l'état viennent du navigateur.
 */
function Checkbox({
  className,
  ...props
}: Omit<React.ComponentProps<"input">, "type">) {
  return (
    <span
      data-slot="checkbox-wrapper"
      className={cn("relative inline-flex size-[22px] flex-none", className)}
    >
      <input
        type="checkbox"
        data-slot="checkbox"
        className="peer absolute inset-0 m-0 cursor-pointer appearance-none rounded-6 border-[1.5px] border-ink-4 bg-ink/0 transition-[background-color,border-color] duration-200 ease-[ease] checked:border-ink checked:bg-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid focus-visible:outline-ring disabled:cursor-not-allowed"
        {...props}
      />
      <CheckIcon
        aria-hidden="true"
        strokeWidth={3}
        className="pointer-events-none absolute inset-0 m-auto size-3.5 scale-40 text-bg opacity-0 [transition:opacity_200ms_ease,scale_var(--dur-swap)_var(--ease-pop)] peer-checked:scale-100 peer-checked:opacity-100"
      />
    </span>
  )
}

export { Checkbox }
