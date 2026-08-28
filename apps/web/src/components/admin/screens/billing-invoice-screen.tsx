"use client";

import { ArrowLeft, ExternalLink, FileDown, Undo2 } from "lucide-react";
import Link from "next/link";
import type * as React from "react";

import { AdminPageHeader, AdminPanel, StatusPill } from "@/components/admin/admin-page";
import { LoadError } from "@/components/admin/load-error";
import { humanise, tone } from "@/components/admin/status-tone";
import { Button } from "@/components/ui/button";
import { adminApi } from "@/lib/admin/api";
import { useAdminResource } from "@/lib/admin/use-admin-resource";
import { formatDate, formatMoney } from "@/lib/utils";

/**
 * One invoice, complete.
 *
 * Somebody is on this page because there is a question about a specific charge —
 * usually with a customer waiting on the answer. So everything that question could
 * be about is here in one load: the lines, the period, the plan and subscription
 * behind it, the card it was taken from, the transaction at the gateway, and the
 * refund with its reason.
 *
 * Nothing on it is editable. An invoice is a financial record: it is corrected by
 * issuing another document, never by rewriting the first one.
 */
export function BillingInvoiceScreen({ id }: { id: number }) {
  const { data, error, loading, reload } = useAdminResource(
    () => adminApi.billing.invoice(id),
    [id],
  );

  if (error) return <LoadError error={error} onRetry={reload} />;

  if (loading && !data) {
    return (
      <div
        className="h-96 animate-pulse rounded-[var(--radius-lg)] bg-[var(--color-surface-sunken)]"
        aria-hidden="true"
      />
    );
  }

  if (!data) return null;

  const invoice = data;
  const currency = invoice.currency;

  return (
    <>
      <AdminPageHeader
        eyebrow="Billing · Invoices"
        title={invoice.number ?? `Invoice #${invoice.id}`}
        description={
          invoice.issued_at
            ? `Issued ${formatDate(invoice.issued_at)}${invoice.paid_at ? `, paid ${formatDate(invoice.paid_at)}` : ", not yet paid"}.`
            : "Not yet issued."
        }
        actions={
          <>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/admin/billing/invoices">
                <ArrowLeft className="size-4" aria-hidden="true" />
                All invoices
              </Link>
            </Button>

            {invoice.pdf_url && (
              <Button variant="secondary" size="sm" asChild>
                <a href={invoice.pdf_url} target="_blank" rel="noreferrer">
                  <FileDown className="size-4" aria-hidden="true" />
                  PDF
                </a>
              </Button>
            )}

            {invoice.hosted_url && (
              <Button size="sm" asChild>
                <a href={invoice.hosted_url} target="_blank" rel="noreferrer">
                  <ExternalLink className="size-4" aria-hidden="true" />
                  Open at {humanise(invoice.gateway)}
                </a>
              </Button>
            )}
          </>
        }
      />

      {/* The four numbers somebody checks first, before reading anything else. */}
      <div className="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Summary label="Status">
          <span className="flex flex-wrap items-center gap-1.5">
            <StatusPill label={humanise(invoice.status)} tone={tone.invoice(invoice.status)} />
            {invoice.refund && (
              <StatusPill
                label={invoice.refund.is_partial ? "Partly refunded" : "Refunded"}
                tone="warning"
              />
            )}
          </span>
        </Summary>

        <Summary label="Total">
          <span className="tabular text-2xl font-semibold leading-none text-[var(--color-foreground)]">
            {formatMoney(invoice.total, currency)}
          </span>
        </Summary>

        <Summary label="Refunded">
          <span
            className="tabular text-2xl font-semibold leading-none"
            style={{
              color:
                invoice.amount_refunded > 0
                  ? "var(--color-warning)"
                  : "var(--color-foreground-subtle)",
            }}
          >
            {formatMoney(invoice.amount_refunded, currency)}
          </span>
        </Summary>

        <Summary label="Net kept">
          <span className="tabular text-2xl font-semibold leading-none text-[var(--color-foreground)]">
            {formatMoney(invoice.net_total, currency)}
          </span>
        </Summary>
      </div>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        <div className="flex flex-col gap-5">
          <AdminPanel title="What was billed" description="Lines as the gateway recorded them" bodyClassName="p-0">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--color-border-subtle)] text-left">
                  <th scope="col" className="px-4 py-2.5 font-medium text-[var(--color-foreground-subtle)]">
                    Description
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-right font-medium text-[var(--color-foreground-subtle)]">
                    Qty
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-right font-medium text-[var(--color-foreground-subtle)]">
                    Unit
                  </th>
                  <th scope="col" className="px-4 py-2.5 text-right font-medium text-[var(--color-foreground-subtle)]">
                    Amount
                  </th>
                </tr>
              </thead>

              <tbody>
                {invoice.lines.length === 0 ? (
                  <tr>
                    <td
                      colSpan={4}
                      className="px-4 py-8 text-center text-sm text-[var(--color-foreground-subtle)]"
                    >
                      This invoice carries no line detail — only its total.
                    </td>
                  </tr>
                ) : (
                  invoice.lines.map((line) => (
                    <tr key={line.id} className="border-b border-[var(--color-border-subtle)] last:border-0">
                      <td className="px-4 py-2.5 text-[var(--color-foreground)]">
                        {line.description}
                      </td>
                      <td className="tabular px-4 py-2.5 text-right text-[var(--color-foreground-muted)]">
                        {line.quantity}
                      </td>
                      <td className="tabular px-4 py-2.5 text-right text-[var(--color-foreground-muted)]">
                        {formatMoney(line.unit_amount, currency)}
                      </td>
                      <td className="tabular px-4 py-2.5 text-right font-medium text-[var(--color-foreground)]">
                        {formatMoney(line.amount, currency)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>

              <tfoot className="border-t border-[var(--color-border-subtle)]">
                <Total label="Subtotal" value={formatMoney(invoice.subtotal, currency)} />
                <Total label="Tax" value={formatMoney(invoice.tax, currency)} />
                <Total label="Total" value={formatMoney(invoice.total, currency)} emphasis />
                {invoice.amount_refunded > 0 && (
                  <>
                    <Total
                      label="Refunded"
                      value={`− ${formatMoney(invoice.amount_refunded, currency)}`}
                      tone="var(--color-warning)"
                    />
                    <Total label="Net" value={formatMoney(invoice.net_total, currency)} emphasis />
                  </>
                )}
              </tfoot>
            </table>
          </AdminPanel>

          {invoice.refund && (
            <AdminPanel
              title="Refund"
              description="Money returned, and the reason recorded with it"
              action={<StatusPill label={invoice.refund.is_partial ? "Partial" : "Full"} tone="warning" />}
            >
              <dl className="flex flex-col gap-3">
                <Row label="Amount">
                  <span className="tabular font-medium text-[var(--color-warning)]">
                    {formatMoney(invoice.refund.amount, currency)}
                  </span>
                </Row>

                <Row label="Refunded">
                  {invoice.refund.refunded_at ? formatDate(invoice.refund.refunded_at) : "—"}
                </Row>

                <Row label="Reason">
                  {invoice.refund.reason ? (
                    <span className="text-[var(--color-foreground)]">{invoice.refund.reason}</span>
                  ) : (
                    // A refund with no reason is a real state, and saying so is more
                    // use than a dash: it tells whoever is looking that the record is
                    // incomplete rather than that the field does not exist.
                    <span className="flex items-center gap-1.5 text-[var(--color-foreground-subtle)]">
                      <Undo2 className="size-3.5" aria-hidden="true" />
                      No reason was recorded with this refund.
                    </span>
                  )}
                </Row>

                {invoice.refund.reference !== undefined && (
                  <Row label="Refund reference">
                    <Reference value={invoice.refund.reference} />
                  </Row>
                )}
              </dl>
            </AdminPanel>
          )}

          <AdminPanel title="Payment" description="How the money moved, and where to find it">
            <dl className="flex flex-col gap-3">
              <Row label="Gateway">{humanise(invoice.gateway)}</Row>

              <Row label="Method">
                {invoice.payment_method ? (
                  <span>
                    {invoice.payment_method.brand
                      ? `${humanise(invoice.payment_method.brand)} ···· ${invoice.payment_method.last4 ?? "????"}`
                      : humanise(invoice.payment_method.type ?? "unknown")}
                  </span>
                ) : (
                  <span className="text-[var(--color-foreground-subtle)]">
                    Nothing has paid this invoice yet.
                  </span>
                )}
              </Row>

              {invoice.transaction_id !== undefined && (
                <Row label="Transaction ID">
                  <Reference value={invoice.transaction_id} />
                </Row>
              )}

              {invoice.transaction_url !== undefined && invoice.transaction_url && (
                <Row label="At the gateway">
                  <a
                    href={invoice.transaction_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 text-[var(--color-primary)] hover:underline"
                  >
                    Open this transaction
                    <ExternalLink className="size-3.5" aria-hidden="true" />
                  </a>
                </Row>
              )}

              <Row label="Paid">{invoice.paid_at ? formatDate(invoice.paid_at) : "—"}</Row>
            </dl>
          </AdminPanel>
        </div>

        <div className="flex flex-col gap-5">
          <AdminPanel title="Customer" description="Who this was billed to">
            <dl className="flex flex-col gap-3">
              <Row label="Name">{invoice.billing_name ?? "—"}</Row>
              <Row label="Email">{invoice.billing_email ?? "—"}</Row>
              <Row label="Account">
                {invoice.user ? (
                  <Link
                    href={`/admin/users/${invoice.user.id}`}
                    className="text-[var(--color-primary)] hover:underline"
                  >
                    {invoice.user.display_name}
                  </Link>
                ) : (
                  <span className="text-[var(--color-foreground-subtle)]">
                    The account was deleted; the invoice remains.
                  </span>
                )}
              </Row>
            </dl>
          </AdminPanel>

          <AdminPanel title="What it was for" description="Plan, subscription and period">
            <dl className="flex flex-col gap-3">
              <Row label="Plan">
                {invoice.plan ? (
                  <Link
                    href={`/admin/billing/plans/${invoice.plan.id}`}
                    className="text-[var(--color-primary)] hover:underline"
                  >
                    {invoice.plan.name}
                  </Link>
                ) : (
                  "—"
                )}
              </Row>

              {invoice.plan && (
                <Row label="List price">
                  <span className="tabular">
                    {formatMoney(invoice.plan.amount, invoice.plan.currency)}
                    {invoice.plan.billing_mode === "one_time"
                      ? " one-off"
                      : ` / ${invoice.plan.interval ?? "period"}`}
                  </span>
                </Row>
              )}

              <Row label="Subscription">
                {invoice.subscription ? (
                  <Link
                    href="/admin/billing/subscriptions"
                    className="inline-flex items-center gap-2 text-[var(--color-primary)] hover:underline"
                  >
                    #{invoice.subscription.id}
                    <StatusPill
                      label={humanise(invoice.subscription.status)}
                      tone={tone.subscription(invoice.subscription.status)}
                    />
                  </Link>
                ) : (
                  <span className="text-[var(--color-foreground-subtle)]">
                    A one-off purchase, not a renewal.
                  </span>
                )}
              </Row>

              <Row label="Period">
                {invoice.period_start && invoice.period_end
                  ? `${formatDate(invoice.period_start)} → ${formatDate(invoice.period_end)}`
                  : "—"}
              </Row>
            </dl>
          </AdminPanel>
        </div>
      </div>
    </>
  );
}

function Summary({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="app-card flex flex-col gap-2 p-4">
      <p className="font-mono text-[0.625rem] font-medium uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {label}
      </p>
      {children}
    </div>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 border-b border-[var(--color-border-subtle)] pb-3 last:border-0 last:pb-0">
      <dt className="text-xs text-[var(--color-foreground-subtle)]">{label}</dt>
      <dd className="min-w-0 text-right text-sm text-[var(--color-foreground-muted)]">{children}</dd>
    </div>
  );
}

/** A gateway identifier: monospaced, selectable, and honest when it is absent. */
function Reference({ value }: { value: string | null | undefined }) {
  if (!value) {
    return <span className="text-[var(--color-foreground-subtle)]">—</span>;
  }

  return (
    <code className="select-all break-all font-mono text-xs text-[var(--color-foreground)]">
      {value}
    </code>
  );
}

function Total({
  label,
  value,
  emphasis = false,
  tone: colorTone,
}: {
  label: string;
  value: string;
  emphasis?: boolean;
  tone?: string;
}) {
  return (
    <tr>
      <td colSpan={3} className="px-4 py-1.5 text-right text-xs text-[var(--color-foreground-subtle)]">
        {label}
      </td>
      <td
        className="tabular px-4 py-1.5 text-right text-sm"
        style={{
          color: colorTone ?? (emphasis ? "var(--color-foreground)" : "var(--color-foreground-muted)"),
          fontWeight: emphasis ? 600 : 400,
        }}
      >
        {value}
      </td>
    </tr>
  );
}
