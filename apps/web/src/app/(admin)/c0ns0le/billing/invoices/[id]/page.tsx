import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { RequireStaff } from "@/components/admin/require-staff";
import { BillingInvoiceScreen } from "@/components/admin/screens/billing-invoice-screen";

export const metadata: Metadata = { title: "Invoice" };

export default async function AdminInvoicePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  if (!/^\d+$/.test(id)) notFound();

  return (
    <RequireStaff permissions={["invoices.view"]}>
      <BillingInvoiceScreen id={Number(id)} />
    </RequireStaff>
  );
}
