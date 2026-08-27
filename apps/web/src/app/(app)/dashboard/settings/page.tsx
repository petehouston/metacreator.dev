import type { Metadata } from "next";

import { ProfileSettings } from "@/components/account/profile-settings";
import { SettingsSection } from "@/components/account/settings-section";

export const metadata: Metadata = { title: "Profile" };

export default function ProfileSettingsPage() {
  return (
    <SettingsSection
      title="Profile"
      description="How you appear across MetaCreator, and the timezone your daily quota resets in."
    >
      <ProfileSettings />
    </SettingsSection>
  );
}
