import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { GrantEditorScreen } from "@/components/admin/screens/grant-editor-screen";

export const metadata: Metadata = { title: "Grant access" };

export default function AdminNewGrantPage() {
  return (
    <RequireStaff permissions={["tool_grants.create"]}>
      <GrantEditorScreen />
    </RequireStaff>
  );
}
