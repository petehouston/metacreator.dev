"use client";

import { ThemeProvider as NextThemesProvider } from "next-themes";
import type * as React from "react";

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  return (
    <NextThemesProvider
      attribute="class"
      defaultTheme="system"
      enableSystem
      // Transitions during a theme swap look like a glitch rather than a nicety.
      disableTransitionOnChange
    >
      {children}
    </NextThemesProvider>
  );
}
