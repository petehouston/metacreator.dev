import { redirect } from "next/navigation";

/**
 * Billing has four screens now, not one with tabs — so `/admin/billing` is a
 * section, not a page. Old links and bookmarks land on the plans list rather than
 * on a 404.
 */
export default function AdminBillingPage() {
  redirect("/admin/billing/plans");
}
