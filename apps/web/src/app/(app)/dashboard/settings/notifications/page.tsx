import type { Metadata } from "next";

import { NotificationPreferences } from "@/components/account/notification-preferences";
import { SettingsSection } from "@/components/account/settings-section";

export const metadata: Metadata = { title: "Notifications" };

export default function NotificationPreferencesPage() {
  return (
    <SettingsSection
      title="What reaches you"
      description="Preferences can only ever remove a channel — security alerts are always sent."
    >
      <NotificationPreferences />
    </SettingsSection>
  );
}
