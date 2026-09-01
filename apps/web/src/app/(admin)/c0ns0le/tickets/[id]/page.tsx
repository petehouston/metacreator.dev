import type { Metadata } from "next";

import { RequireStaff } from "@/components/admin/require-staff";
import { TicketDetailScreen } from "@/components/admin/screens/ticket-detail-screen";

export const metadata: Metadata = { title: "Ticket" };

export default async function AdminTicketPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return (
    <RequireStaff permissions={["tickets.view"]}>
      <TicketDetailScreen id={id} />
    </RequireStaff>
  );
}
