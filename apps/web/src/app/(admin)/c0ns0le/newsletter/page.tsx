import type { Metadata } from "next";

import { NewsletterScreen } from "@/components/admin/screens/newsletter-screen";
import { RequireStaff } from "@/components/admin/require-staff";

export const metadata: Metadata = { title: "Newsletter" };

export default function AdminNewsletterPage() {
  return (
    <RequireStaff permissions={["newsletter.view"]}>
      <NewsletterScreen />
    </RequireStaff>
  );
}
