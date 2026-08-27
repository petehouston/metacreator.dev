import type { Metadata } from "next";
import type * as React from "react";

import { AppPageHeader } from "@/components/app/page-header";
import { SettingsTabs } from "@/components/app/settings-tabs";

export const metadata: Metadata = { title: { default: "Settings", template: "%s | Settings" } };

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <AppPageHeader
        eyebrow="Account"
        title="Settings"
        description="Your profile, how you sign in, and what we're allowed to send you."
      />

      <SettingsTabs />

      <div className="max-w-3xl">{children}</div>
    </>
  );
}
