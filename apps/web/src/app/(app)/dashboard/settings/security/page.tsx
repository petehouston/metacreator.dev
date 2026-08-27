import type { Metadata } from "next";

import { DeviceSettings } from "@/components/account/device-settings";
import { PasswordSettings } from "@/components/account/password-settings";
import { SettingsSection } from "@/components/account/settings-section";

export const metadata: Metadata = { title: "Security" };

export default function SecuritySettingsPage() {
  return (
    <>
      <SettingsSection
        title="Password"
        description="Changing it signs out every other device."
      >
        <PasswordSettings />
      </SettingsSection>

      <SettingsSection
        title="Devices"
        description="Browsers with an active session. Revoke anything you don't recognise."
      >
        <DeviceSettings />
      </SettingsSection>
    </>
  );
}
