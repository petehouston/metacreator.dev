import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { SitemapScreen } from "@/components/admin/screens/sitemap-screen";

export const metadata: Metadata = { title: "Sitemap" };

export default function AdminSitemapPage() {
  return (
    <RequireStaff permissions={["settings.view"]}>
      <SitemapScreen />
    </RequireStaff>
  );
}
