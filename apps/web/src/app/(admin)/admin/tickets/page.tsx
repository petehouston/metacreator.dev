import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { TicketsScreen } from "@/components/admin/screens/tickets-screen";

export const metadata: Metadata = { title: "Tickets" };

export default function AdminTicketsPage() {
  return (
    <RequireStaff permissions={["tickets.view_any"]}>
      <TicketsScreen />
    </RequireStaff>
  );
}
