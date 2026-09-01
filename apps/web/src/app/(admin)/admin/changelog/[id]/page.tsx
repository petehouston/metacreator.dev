import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { ChangelogEditorScreen } from "@/components/admin/screens/changelog-editor-screen";

export const metadata: Metadata = { title: "Edit release" };

export default async function EditReleasePage({
  params,
}: PageProps<"/admin/changelog/[id]">) {
  const { id } = await params;

  return (
    <RequireStaff permissions={["changelog.update"]}>
      <ChangelogEditorScreen id={id} />
    </RequireStaff>
  );
}
