import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { ChangelogEditorScreen } from "@/components/admin/screens/changelog-editor-screen";

export const metadata: Metadata = { title: "New release" };

export default function NewReleasePage() {
  return (
    <RequireStaff permissions={["changelog.create"]}>
      <ChangelogEditorScreen />
    </RequireStaff>
  );
}
