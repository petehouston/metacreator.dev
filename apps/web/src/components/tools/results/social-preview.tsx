"use client";

import { ExternalLink } from "lucide-react";
import * as React from "react";

import { CopyButton } from "@/components/ui/copy-button";
import { cn } from "@/lib/utils";
import type { ToolResult } from "@/lib/types";

/**
 * Mock-ups of a post, profile, link card or Pin as the platform draws it.
 *
 * A preview tool's whole value is visual. "477 characters" is a fact; seeing your
 * call to action sitting under "See more" is a decision. Every platform fact —
 * where the fold is, which card shape a tag produces, how wide the chrome is — is
 * decided in PHP and arrives in the frame, so this file only draws what it is told
 * and adding a preview tool still needs no frontend work.
 */

interface Frame {
  platform: string;
  surface: string;
  kind: "post" | "profile" | "channel" | "link-card" | "pin" | "safe-zone";
  author?: { name: string; handle?: string; meta?: string; initials?: string };
  body?: {
    visible: string;
    hidden: string;
    full: string;
    more_label: string;
    characters: number;
  };
  media?: { aspect?: string; label?: string };
  artwork?: { banner?: string; avatar?: string };
  cta?: { label: string; url: string };
  link?: {
    domain: string;
    title?: string;
    description?: string;
    style?: "large" | "small";
    image?: string;
  };
  actions?: string[];
  status?: { tone: "ok" | "warn" | "danger"; label: string };
  details?: { label: string; value: string }[];
  note?: string;
  canvas?: {
    width: number;
    height: number;
    top: number;
    bottom: number;
    left: number;
    right: number;
  };
}

/** Each platform's own accent, used for the avatar and the frame's identity. */
const ACCENTS: Record<string, string> = {
  facebook: "#1877F2",
  instagram: "#E1306C",
  linkedin: "#0A66C2",
  x: "#0F1419",
  threads: "#000000",
  pinterest: "#E60023",
  tiktok: "#00C4CC",
  youtube: "#FF0033",
  generic: "var(--color-foreground-muted)",
};

const ASPECTS: Record<string, string> = {
  "1:1": "1 / 1",
  "4:5": "4 / 5",
  "2:3": "2 / 3",
  "4:3": "4 / 3",
  "9:16": "9 / 16",
  "1.91:1": "1.91 / 1",
  "1.2:1": "1.2 / 1",
};

export function SocialPreviewResult({ result }: { result: ToolResult }) {
  const frames = (result.data.frames ?? []) as Frame[];
  const table = result.data.table as
    | { columns: { key: string; label: string }[]; rows: Record<string, unknown>[] }
    | undefined;

  if (frames.length === 0) return null;

  return (
    <div className="flex flex-col gap-6">
      <div
        className={cn(
          "grid gap-5",
          frames.length > 1 && "sm:grid-cols-2",
          frames.length > 3 && "lg:grid-cols-3",
        )}
      >
        {frames.map((frame, index) => (
          <FrameCard key={`${frame.surface}-${index}`} frame={frame} />
        ))}
      </div>

      {table && <EvidenceTable columns={table.columns} rows={table.rows} />}
    </div>
  );
}

function FrameCard({ frame }: { frame: Frame }) {
  const accent = ACCENTS[frame.platform] ?? ACCENTS.generic;

  return (
    <figure className="flex flex-col gap-3">
      <figcaption className="flex items-center justify-between gap-3">
        <span className="flex items-center gap-2">
          <span
            aria-hidden="true"
            className="size-2 rounded-full"
            style={{ backgroundColor: accent }}
          />
          <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
            {frame.surface}
          </span>
        </span>

        {frame.status && <StatusBadge status={frame.status} />}
      </figcaption>

      <div className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface-solid)] shadow-[var(--shadow-raised)]">
        {frame.kind === "safe-zone" ? (
          <SafeZone frame={frame} accent={accent} />
        ) : frame.kind === "channel" ? (
          <Channel frame={frame} accent={accent} />
        ) : frame.kind === "link-card" ? (
          <LinkCard frame={frame} />
        ) : frame.kind === "pin" ? (
          <Pin frame={frame} />
        ) : (
          <Post frame={frame} accent={accent} />
        )}
      </div>

      {/* A channel frame draws its own counts inline, next to the name. */}
      {frame.kind !== "channel" && frame.details && frame.details.length > 0 && (
        <dl className="flex flex-wrap gap-x-4 gap-y-1">
          {frame.details.map((detail) => (
            <div key={detail.label} className="flex items-baseline gap-1.5 text-xs">
              <dt className="text-[var(--color-foreground-subtle)]">{detail.label}</dt>
              <dd className="tabular font-medium text-[var(--color-foreground)]">
                {detail.value}
              </dd>
            </div>
          ))}
        </dl>
      )}

      {frame.note && (
        <p className="text-xs leading-relaxed text-[var(--color-foreground-subtle)]">
          {frame.note}
        </p>
      )}
    </figure>
  );
}

/* ── post and profile ─────────────────────────────────────────────────────── */

function Post({ frame, accent }: { frame: Frame; accent: string }) {
  const profile = frame.kind === "profile";

  return (
    <div className="flex flex-col">
      <div className="flex items-start gap-3 p-4 pb-3">
        <Avatar
          initials={frame.author?.initials ?? "·"}
          accent={accent}
          size={profile ? "lg" : "md"}
        />

        <div className="flex min-w-0 flex-1 flex-col">
          <span className="truncate text-sm font-semibold text-[var(--color-foreground)]">
            {frame.author?.name ?? "Your account"}
          </span>
          <span className="truncate text-xs text-[var(--color-foreground-subtle)]">
            {[frame.author?.handle, frame.author?.meta]
              .filter(Boolean)
              .join(" · ")}
          </span>
        </div>

        {frame.body && (
          <CopyButton value={frame.body.full} label="" copiedLabel="" size="icon" className="size-7" />
        )}
      </div>

      {frame.body && <Body body={frame.body} />}

      {frame.media && <MediaPlaceholder media={frame.media} />}

      {frame.link && <LinkUnfurl link={frame.link} />}

      {frame.actions && frame.actions.length > 0 && (
        <div
          className={cn(
            "flex items-center gap-4 border-t border-[var(--color-border-subtle)] px-4 py-2.5",
            profile && "gap-2",
          )}
        >
          {frame.actions.map((action) => (
            <span
              key={action}
              className={cn(
                "text-xs font-medium text-[var(--color-foreground-subtle)]",
                profile &&
                  "flex-1 rounded-[var(--radius-sm)] border border-[var(--color-border)] py-1.5 text-center",
              )}
            >
              {action}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}

/**
 * The visible slice, then the truncated remainder at a fraction of its opacity.
 *
 * Greying out the hidden text rather than dropping it is the point of the tool: you
 * see both what the reader gets and what you wrote that they will not.
 */
function Body({
  body,
  className,
}: {
  body: NonNullable<Frame["body"]>;
  className?: string;
}) {
  return (
    <p
      className={cn(
        "px-4 pb-3 text-sm leading-relaxed whitespace-pre-wrap break-words text-[var(--color-foreground)]",
        className,
      )}
    >
      {body.visible}
      {body.hidden && (
        <>
          <span className="font-medium text-[var(--color-foreground-subtle)]">
            {body.more_label}
          </span>{" "}
          <span
            className="text-[var(--color-foreground-subtle)] opacity-40"
            title="Hidden until someone taps"
          >
            {body.hidden}
          </span>
        </>
      )}
    </p>
  );
}

function MediaPlaceholder({ media }: { media: NonNullable<Frame["media"]> }) {
  return (
    <div
      className="flex items-center justify-center border-y border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)]"
      style={{ aspectRatio: ASPECTS[media.aspect ?? "1:1"] ?? "1 / 1" }}
    >
      <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        {media.label ?? media.aspect}
      </span>
    </div>
  );
}

/* ── link cards ───────────────────────────────────────────────────────────── */

function LinkCard({ frame }: { frame: Frame }) {
  if (!frame.link) return null;

  return <LinkUnfurl link={frame.link} standalone />;
}

function LinkUnfurl({
  link,
  standalone = false,
}: {
  link: NonNullable<Frame["link"]>;
  standalone?: boolean;
}) {
  const small = link.style === "small";
  const hasText = Boolean(link.title || link.description);

  if (!hasText && !link.image) {
    // A bare domain — an Instagram bio link, say. Nothing to unfurl.
    return (
      <p className={cn("px-4 pb-3 text-sm font-medium text-[var(--color-primary)]", standalone && "p-4")}>
        {link.domain}
      </p>
    );
  }

  return (
    <div
      className={cn(
        "flex overflow-hidden border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)]",
        small ? "items-stretch" : "flex-col",
        standalone ? "" : "m-4 mt-0 rounded-[var(--radius-md)] border",
        standalone && "border-0",
      )}
    >
      <CardImage image={link.image} small={small} />

      <div className="flex min-w-0 flex-1 flex-col justify-center gap-1 p-3">
        <span className="truncate font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          {link.domain}
        </span>

        {link.title && (
          <span className="text-sm leading-snug font-semibold text-[var(--color-foreground)]">
            {link.title}
          </span>
        )}

        {link.description && (
          <span className="text-xs leading-relaxed text-[var(--color-foreground-muted)]">
            {link.description}
          </span>
        )}
      </div>
    </div>
  );
}

/**
 * The card image.
 *
 * `image` is whatever URL the page under test advertises, so it is loaded with no
 * referrer and allowed to fail: a debugger that renders a broken third-party image
 * as an empty box is worse than one that says the image did not load.
 */
function CardImage({ image, small }: { image?: string; small: boolean }) {
  const [failed, setFailed] = React.useState(false);

  const shape = small
    ? "w-20 shrink-0 sm:w-24"
    : "w-full";

  if (!image || failed) {
    return (
      <div
        className={cn(
          "flex items-center justify-center bg-[var(--color-surface-inverse)]/5 text-center",
          shape,
        )}
        style={small ? undefined : { aspectRatio: "1.91 / 1" }}
      >
        <span className="p-2 font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          {image ? "Image failed to load" : "No image"}
        </span>
      </div>
    );
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element -- an arbitrary URL on an unknown host, deliberately unoptimised
    <img
      src={image}
      alt=""
      referrerPolicy="no-referrer"
      loading="lazy"
      onError={() => setFailed(true)}
      className={cn("object-cover", shape)}
      style={small ? undefined : { aspectRatio: "1.91 / 1" }}
    />
  );
}

/* ── channel ──────────────────────────────────────────────────────────────── */

/**
 * A channel header drawn the way YouTube draws it: banner, avatar over the corner
 * of it, name, then the handle and counts on one muted line.
 *
 * The artwork is the point. A verdict about a channel is far easier to trust when
 * you can see, above it, that the tool is looking at the channel you meant — so the
 * banner and avatar are the real images from Google's CDN, loaded with no referrer
 * and each allowed to fail back to a plain surface rather than a broken box.
 */
function Channel({ frame, accent }: { frame: Frame; accent: string }) {
  const meta = [frame.author?.handle, ...(frame.details ?? []).map((d) => d.value)]
    .filter(Boolean)
    .join(" · ");

  return (
    <div className="flex flex-col">
      <ChannelBanner banner={frame.artwork?.banner} accent={accent} />

      <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:gap-4">
        <ChannelAvatar
          avatar={frame.artwork?.avatar}
          initials={frame.author?.initials ?? "·"}
          accent={accent}
        />

        <div className="flex min-w-0 flex-1 flex-col gap-1.5">
          <h3 className="text-heading-3 leading-tight break-words text-[var(--color-foreground)]">
            {frame.author?.name ?? "That channel"}
          </h3>

          {meta && (
            <p className="text-sm text-[var(--color-foreground-muted)]">{meta}</p>
          )}

          {frame.body && (
            <p className="line-clamp-3 text-sm leading-relaxed break-words text-[var(--color-foreground-muted)]">
              {frame.body.full}
            </p>
          )}

          {frame.cta && (
            <a
              href={frame.cta.url}
              target="_blank"
              rel="noopener noreferrer nofollow"
              className="mt-1.5 inline-flex w-fit items-center gap-1.5 rounded-full bg-[var(--color-foreground)] px-4 py-2 text-sm font-medium text-[var(--color-surface-solid)] transition-opacity hover:opacity-85"
            >
              {frame.cta.label}
              <ExternalLink aria-hidden="true" className="size-3.5" />
            </a>
          )}
        </div>
      </div>
    </div>
  );
}

/** The banner strip, at the 16:4 crop YouTube shows on a desktop channel page. */
function ChannelBanner({ banner, accent }: { banner?: string; accent: string }) {
  const [failed, setFailed] = React.useState(false);

  if (!banner || failed) {
    return (
      <div
        className="w-full"
        style={{
          aspectRatio: "16 / 3",
          background: `linear-gradient(120deg, color-mix(in oklab, ${accent} 22%, transparent), transparent)`,
        }}
      />
    );
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element -- a Google CDN URL, deliberately unoptimised
    <img
      src={banner}
      alt=""
      referrerPolicy="no-referrer"
      loading="lazy"
      onError={() => setFailed(true)}
      className="w-full object-cover"
      style={{ aspectRatio: "16 / 3" }}
    />
  );
}

function ChannelAvatar({
  avatar,
  initials,
  accent,
}: {
  avatar?: string;
  initials: string;
  accent: string;
}) {
  const [failed, setFailed] = React.useState(false);

  if (!avatar || failed) {
    return (
      <span className="-mt-8 shrink-0 rounded-full ring-4 ring-[var(--color-surface-solid)]">
        <span
          aria-hidden="true"
          className="flex size-16 items-center justify-center rounded-full text-lg font-semibold text-white sm:size-20"
          style={{ backgroundColor: accent }}
        >
          {initials}
        </span>
      </span>
    );
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element -- a Google CDN URL, deliberately unoptimised
    <img
      src={avatar}
      alt=""
      referrerPolicy="no-referrer"
      loading="lazy"
      onError={() => setFailed(true)}
      className="-mt-8 size-16 shrink-0 rounded-full object-cover ring-4 ring-[var(--color-surface-solid)] sm:size-20"
    />
  );
}

/* ── Pinterest ────────────────────────────────────────────────────────────── */

function Pin({ frame }: { frame: Frame }) {
  return (
    <div className="flex flex-col">
      <div
        className="flex items-center justify-center border-b border-[var(--color-border-subtle)] bg-[var(--color-surface-sunken)]"
        style={{ aspectRatio: ASPECTS[frame.media?.aspect ?? "2:3"] ?? "2 / 3" }}
      >
        <span className="font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
          {frame.media?.label ?? "Your Pin image"}
        </span>
      </div>

      <div className="flex flex-col gap-2 p-4">
        {frame.link?.domain && (
          <span className="truncate text-xs text-[var(--color-foreground-subtle)]">
            {frame.link.domain}
          </span>
        )}

        {frame.body && (
          <Body body={frame.body} className="p-0 text-sm font-semibold" />
        )}

        {frame.author && (
          <span className="flex items-center gap-2 text-xs text-[var(--color-foreground-subtle)]">
            <Avatar initials={frame.author.initials ?? "·"} accent={ACCENTS.pinterest} size="sm" />
            {frame.author.name}
          </span>
        )}
      </div>
    </div>
  );
}

/* ── safe zones ───────────────────────────────────────────────────────────── */

/**
 * The canvas with the app's own chrome shaded over it, drawn to scale.
 *
 * Margins arrive in pixels on the real canvas and become percentages here, so the
 * shape stays honest at whatever size the card ends up.
 */
function SafeZone({ frame, accent }: { frame: Frame; accent: string }) {
  const canvas = frame.canvas;

  if (!canvas) return null;

  const percent = (value: number, total: number) => `${(value / total) * 100}%`;

  return (
    <div
      className="relative w-full bg-[var(--color-surface-sunken)]"
      style={{ aspectRatio: `${canvas.width} / ${canvas.height}` }}
      role="img"
      aria-label={`${frame.surface}: safe area inset by ${canvas.top} pixels top, ${canvas.bottom} bottom, ${canvas.left} left and ${canvas.right} right on a ${canvas.width} by ${canvas.height} canvas.`}
    >
      <div
        className="absolute rounded-[2px] border-2 border-dashed"
        style={{
          top: percent(canvas.top, canvas.height),
          bottom: percent(canvas.bottom, canvas.height),
          left: percent(canvas.left, canvas.width),
          right: percent(canvas.right, canvas.width),
          borderColor: accent,
          backgroundColor: "var(--color-surface-solid)",
        }}
      />

      {/* Diagonal hatching reads as "covered" without hiding the geometry under it. */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0"
        style={{
          backgroundImage:
            "repeating-linear-gradient(45deg, var(--color-foreground-subtle) 0 1px, transparent 1px 7px)",
          opacity: 0.35,
          maskImage: `linear-gradient(#000 0 0)`,
          clipPath: `polygon(0% 0%, 0% 100%, ${percent(canvas.left, canvas.width)} 100%, ${percent(canvas.left, canvas.width)} ${percent(canvas.top, canvas.height)}, ${percent(canvas.width - canvas.right, canvas.width)} ${percent(canvas.top, canvas.height)}, ${percent(canvas.width - canvas.right, canvas.width)} ${percent(canvas.height - canvas.bottom, canvas.height)}, ${percent(canvas.left, canvas.width)} ${percent(canvas.height - canvas.bottom, canvas.height)}, ${percent(canvas.left, canvas.width)} 100%, 100% 100%, 100% 0%)`,
        }}
      />

      <span className="absolute inset-x-0 top-1/2 -translate-y-1/2 text-center font-mono text-[0.625rem] uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]">
        Safe area
      </span>
    </div>
  );
}

/* ── shared parts ─────────────────────────────────────────────────────────── */

function Avatar({
  initials,
  accent,
  size = "md",
}: {
  initials: string;
  accent: string;
  size?: "sm" | "md" | "lg";
}) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        "flex shrink-0 items-center justify-center rounded-full font-semibold text-white",
        size === "sm" && "size-5 text-[0.5rem]",
        size === "md" && "size-9 text-xs",
        size === "lg" && "size-12 text-sm",
      )}
      style={{ backgroundColor: accent }}
    >
      {initials}
    </span>
  );
}

function StatusBadge({ status }: { status: NonNullable<Frame["status"]> }) {
  const color = {
    ok: "var(--color-success)",
    warn: "var(--color-warning)",
    danger: "var(--color-danger)",
  }[status.tone];

  return (
    <span
      className="rounded-full px-2 py-0.5 text-[0.6875rem] font-medium"
      style={{ color, backgroundColor: `color-mix(in oklab, ${color} 12%, transparent)` }}
    >
      {status.label}
    </span>
  );
}

/** The facts behind the picture: tags, margins, limits. */
function EvidenceTable({
  columns,
  rows,
}: {
  columns: { key: string; label: string }[];
  rows: Record<string, unknown>[];
}) {
  return (
    <div className="overflow-x-auto rounded-[var(--radius-md)] border border-[var(--color-border)]">
      <table className="w-full min-w-[36rem] text-sm">
        <thead className="bg-[var(--color-surface-sunken)]">
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className="px-4 py-3 text-left font-mono text-[0.625rem] font-semibold uppercase tracking-[0.12em] text-[var(--color-foreground-subtle)]"
              >
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={index} className="border-t border-[var(--color-border-subtle)]">
              {columns.map((column) => (
                <td
                  key={column.key}
                  className="px-4 py-3 break-words text-[var(--color-foreground)]"
                >
                  {String(row[column.key] ?? "—")}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
