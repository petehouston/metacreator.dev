import type { Metadata } from "next";

import { MediaScreen } from "@/components/admin/screens/media-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Media" };

export default function AdminMediaPage() {
  return (
    <RequireStaff permissions={["media.view_any"]}>
      <MediaScreen />
    </RequireStaff>
  );
}
