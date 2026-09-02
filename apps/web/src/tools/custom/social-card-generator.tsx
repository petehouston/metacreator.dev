"use client";

import { Download, ImagePlus, Trash2 } from "lucide-react";
import * as React from "react";

import type { CustomToolProps } from "@/tools/custom";
import type { JsonSchemaProperty } from "@/lib/types";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import {
  drawCard,
  type CardPlatform,
  type CardValues,
} from "@/tools/custom/social-card";
import { cn } from "@/lib/utils";

/**
 * The workspace behind every mock-up card generator.
 *
 * One component rather than five, because the five differ only in which fields
 * they collect and how the card is painted — and both of those are already
 * described somewhere else. The fields come from the tool's own input schema, the
 * same JSON Schema the API validates against, so a field added to a runner appears
 * here without anybody editing this file. The painting lives in `social-card.ts`,
 * keyed on the platform.
 *
 * Why a custom UI at all, when the engine would happily generate the form (docs/08):
 *
 * - **Nothing is uploaded.** An avatar someone drops in is read with `FileReader`
 *   and drawn straight onto the canvas. There is no request to inspect, nothing on
 *   our side to store, and nothing to promise to delete.
 * - **The preview has to be live.** Nobody sets a like count, a theme and a device
 *   width blind and then submits a form to find out what they got.
 * - **The export formats are the browser's.** PNG, JPG, WebP and AVIF all come out
 *   of `canvas.toBlob`, which the server has no equivalent for without shipping an
 *   encoder — and the file never leaves the machine either way.
 */

const FORMATS = [
  { label: "PNG", mime: "image/png", extension: "png" },
  { label: "JPG", mime: "image/jpeg", extension: "jpg" },
  { label: "WebP", mime: "image/webp", extension: "webp" },
  // Supported by current Chrome and Firefox; browsers without an encoder fall
  // through to the "could not produce that format" path below rather than
  // silently handing over a PNG named .avif.
  { label: "AVIF", mime: "image/avif", extension: "avif" },
] as const;

const MAX_AVATAR_BYTES = 8 * 1024 * 1024;

/** Export multipliers. 2× is sharp on a retina slide; 3× is for print and thumbnails. */
const SCALES = [1, 2, 3] as const;

/**
 * Which layout a tool key draws.
 *
 * Keyed on the registry key rather than the slug, for the same reason
 * `renderCustomTool` is: an admin can rewrite a slug, and a renamed tool should
 * not silently lose its workspace.
 */
const PLATFORMS: Record<string, CardPlatform> = {
  "facebook.post-generator": "facebook",
  "instagram.post-generator": "instagram",
  "x.reply-generator": "x-reply",
  "pinterest.pin-generator": "pinterest",
  "tiktok.comment-generator": "tiktok",
};

/**
 * Fields the workspace handles itself rather than drawing as a form control.
 *
 * `avatar_url` is the runner's way of accepting an avatar over the API. In the
 * browser the equivalent is a local file that is never uploaded, so the URL box is
 * replaced by a picker and the value never leaves this component.
 */
const HANDLED_BY_UI = new Set(["avatar_url"]);

/** Fields that belong in the toolbar over the preview rather than in the form. */
const PRESENTATION = new Set(["device", "theme"]);

export default function SocialCardGenerator({ tool }: CustomToolProps) {
  const platform = PLATFORMS[tool.key] ?? "facebook";
  const properties = tool.input_schema?.properties ?? {};

  const [values, setValues] = React.useState<CardValues>(() => initialValues(tool));
  const [avatar, setAvatar] = useAvatar();
  const [transparent, setTransparent] = React.useState(false);
  const [scale, setScale] = React.useState<(typeof SCALES)[number]>(2);
  const [error, setError] = React.useState<string | null>(null);

  const canvasRef = React.useRef<HTMLCanvasElement>(null);

  // Redraw on every change. A card is a few hundred fill operations, so there is
  // nothing here worth debouncing — a keystroke-to-pixel delay would be felt.
  React.useEffect(() => {
    if (canvasRef.current) {
      drawCard(canvasRef.current, platform, values, { avatar: avatar.image }, { scale, transparent });
    }
  });

  function update(name: string, value: string | number | boolean) {
    setValues((current) => ({ ...current, [name]: value }));
  }

  function download(format: (typeof FORMATS)[number]) {
    const canvas = canvasRef.current;

    if (!canvas) return;

    // JPEG has no alpha channel, and a transparent canvas encodes to *black* in
    // one — which would hand somebody a ruined card and no explanation. Redraw
    // opaque for the encode, then put the preview back.
    const opaque = format.mime === "image/jpeg" && transparent;
    const restore = () => {
      if (opaque) drawCard(canvas, platform, values, { avatar: avatar.image }, { scale, transparent });
    };

    if (opaque) {
      drawCard(canvas, platform, values, { avatar: avatar.image }, { scale, transparent: false });
    }

    canvas.toBlob(
      (blob) => {
        restore();

        if (!blob) {
          setError(`Your browser could not produce ${format.label}. Try PNG.`);

          return;
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = `${tool.slug}-${String(values.device ?? "desktop")}.${format.extension}`;
        link.click();

        // Revoked on the next tick rather than immediately: the click is
        // synchronous but the fetch the browser does for it is not.
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        setError(null);
      },
      format.mime,
      format.mime === "image/png" ? undefined : 0.95,
    );
  }

  const formFields = Object.entries(properties).filter(
    ([name]) => !HANDLED_BY_UI.has(name) && !PRESENTATION.has(name),
  );

  return (
    <div className="flex flex-col gap-6">
      <section
        aria-labelledby="card-input-heading"
        className="panel relative overflow-hidden p-5 shadow-[var(--shadow-card)] sm:p-7"
      >
        <span
          aria-hidden="true"
          className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--color-primary)] to-transparent opacity-70"
        />

        <h2 id="card-input-heading" className="sr-only">
          Card details
        </h2>

        <div className="grid gap-5 sm:grid-cols-2">
          {formFields.map(([name, property]) => (
            <SchemaControl
              key={name}
              name={name}
              property={property}
              value={values[name]}
              onChange={(value) => update(name, value)}
            />
          ))}

          {"avatar_url" in properties && (
            <AvatarPicker state={avatar} onPick={(file) => setAvatar(file, setError)} />
          )}
        </div>

        {error && (
          <p role="alert" className="mt-5 text-sm font-medium text-[var(--color-danger)]">
            {error}
          </p>
        )}

        <p className="mt-6 text-xs text-[var(--color-foreground-subtle)]">
          Everything here is drawn in your browser. An image you add is never uploaded and nothing
          is stored — close the tab and it is gone.
        </p>
      </section>

      {/* ── The card, and the ways to take it away. ───────────────────────── */}
      <section
        aria-labelledby="card-preview-heading"
        className="panel overflow-hidden shadow-[var(--shadow-card)]"
      >
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-border-subtle)] px-5 py-3.5 sm:px-6">
          <h2 id="card-preview-heading" className="text-heading-3">
            Preview
          </h2>

          <div className="flex flex-wrap items-center gap-2">
            {PRESENTATION_ORDER.filter((name) => name in properties).map((name) => (
              <SegmentedControl
                key={name}
                label={properties[name].title ?? name}
                options={properties[name].enum ?? []}
                value={String(values[name] ?? "")}
                onChange={(value) => update(name, value)}
              />
            ))}
          </div>
        </header>

        <div className="p-5 sm:p-6">
          <div
            className={cn(
              "overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border-subtle)]",
              // A transparent card needs something behind it or it is invisible on
              // whichever theme the site itself is in.
              transparent &&
                "bg-[repeating-conic-gradient(var(--color-surface-sunken)_0_25%,transparent_0_50%)] bg-[length:20px_20px]",
            )}
          >
            <canvas ref={canvasRef} className="block w-full" role="img" aria-label={describe(tool.name, values)} />
          </div>

          <div className="mt-5 flex flex-wrap items-center gap-3">
            {FORMATS.map((format) => (
              <Button key={format.mime} type="button" variant="secondary" onClick={() => download(format)}>
                <Download />
                {format.label}
              </Button>
            ))}

            <SegmentedControl
              label="Export size"
              options={SCALES.map((value) => `${value}×`)}
              value={`${scale}×`}
              onChange={(value) => setScale(Number(value.replace("×", "")) as (typeof SCALES)[number])}
            />
          </div>

          <div className="mt-4">
            <Checkbox
              id="card-transparent"
              label="Transparent background"
              hint="PNG, WebP and AVIF only — a JPG is always drawn on the theme colour."
              checked={transparent}
              onChange={(event) => setTransparent(event.target.checked)}
            />
          </div>

          <p className="mt-4 text-xs text-[var(--color-foreground-subtle)]">
            This draws whatever you type, so it is a mock-up, not proof. Do not present a card as a
            screenshot of something somebody actually posted.
          </p>
        </div>
      </section>
    </div>
  );
}

/** Device before theme in the toolbar: it changes the layout, theme only the paint. */
const PRESENTATION_ORDER = ["device", "theme"];

/**
 * The starting state.
 *
 * The catalog's worked example is placeholder text elsewhere on the site; here it
 * is the initial state, because an empty canvas teaches nothing about what the
 * tool does. Schema defaults fill whatever the example leaves out.
 */
function initialValues(tool: CustomToolProps["tool"]): CardValues {
  const properties = tool.input_schema?.properties ?? {};
  const example = (tool.example?.input ?? {}) as Record<string, unknown>;
  const values: CardValues = {};

  for (const [name, property] of Object.entries(properties)) {
    const candidate = example[name] ?? property.default;

    if (typeof candidate === "string" || typeof candidate === "number" || typeof candidate === "boolean") {
      values[name] = candidate;
      continue;
    }

    values[name] = property.type === "boolean" ? false : property.type === "integer" ? 0 : "";
  }

  return values;
}

function describe(name: string, values: CardValues): string {
  const body = [values.text, values.caption, values.content, values.reply_text, values.title]
    .filter((value) => typeof value === "string" && value !== "")
    .join(" ");

  return body === "" ? name : `${name}: ${body}`;
}

/** One form control, chosen from the schema the API already validates against. */
function SchemaControl({
  name,
  property,
  value,
  onChange,
}: {
  name: string;
  property: JsonSchemaProperty;
  value: string | number | boolean | undefined;
  onChange: (value: string | number | boolean) => void;
}) {
  const id = `card-${name}`;
  const label = property.title ?? name;

  if (property.type === "boolean") {
    return (
      <div className="min-w-0 self-end">
        <Checkbox
          id={id}
          label={label}
          hint={property.description}
          checked={value === true}
          onChange={(event) => onChange(event.target.checked)}
        />
      </div>
    );
  }

  if (Array.isArray(property.enum) && property.enum.length > 0) {
    return (
      <Field id={id} label={label} hint={property.description} className="min-w-0">
        {(aria) => (
          <Select {...aria} value={String(value ?? "")} onChange={(event) => onChange(event.target.value)}>
            {property.enum?.map((option) => (
              <option key={option} value={option}>
                {option.charAt(0).toUpperCase() + option.slice(1)}
              </option>
            ))}
          </Select>
        )}
      </Field>
    );
  }

  if (property.type === "integer" || property.type === "number") {
    return (
      <Field id={id} label={label} hint={property.description} className="min-w-0">
        {(aria) => (
          <Input
            {...aria}
            type="number"
            inputMode="numeric"
            min={property.minimum}
            max={property.maximum}
            value={String(value ?? 0)}
            onChange={(event) => onChange(Number(event.target.value) || 0)}
          />
        )}
      </Field>
    );
  }

  const multiline = property["x-control"] === "textarea" || (property.maxLength ?? 0) > 300;

  return (
    <Field
      id={id}
      label={label}
      hint={property.description}
      className={cn("min-w-0", multiline && "sm:col-span-2")}
    >
      {(aria) =>
        multiline ? (
          <Textarea
            {...aria}
            rows={3}
            className="min-h-24"
            maxLength={property.maxLength}
            placeholder={String(property.examples?.[0] ?? "")}
            value={String(value ?? "")}
            onChange={(event) => onChange(event.target.value)}
          />
        ) : (
          <Input
            {...aria}
            maxLength={property.maxLength}
            placeholder={String(property.examples?.[0] ?? "")}
            value={String(value ?? "")}
            onChange={(event) => onChange(event.target.value)}
          />
        )
      }
    </Field>
  );
}

/** A small radio group drawn as a pill row, for the two or three presentation choices. */
function SegmentedControl({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: readonly string[];
  value: string;
  onChange: (value: string) => void;
}) {
  if (options.length === 0) return null;

  return (
    <div
      role="radiogroup"
      aria-label={label}
      className="inline-flex rounded-[var(--radius-md)] border border-[var(--color-border)] p-1"
    >
      {options.map((option) => (
        <button
          key={option}
          type="button"
          role="radio"
          aria-checked={value === option}
          onClick={() => onChange(option)}
          className={cn(
            "rounded-[var(--radius-sm)] px-3 py-1 text-xs capitalize transition-colors",
            value === option
              ? "bg-[var(--color-primary)] text-[var(--color-primary-foreground)]"
              : "text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
          )}
        >
          {option}
        </button>
      ))}
    </div>
  );
}

interface AvatarState {
  image: HTMLImageElement | null;
  preview: string | null;
}

/**
 * A dropped image, decoded and held in memory.
 *
 * The data URL never leaves this hook: it feeds the canvas and the thumbnail, and
 * it is dropped when the component unmounts. Nothing is uploaded, so there is no
 * request to inspect and no bucket to worry about.
 */
function useAvatar(): [AvatarState, (file: File | null, onError: (message: string) => void) => void] {
  const [state, setState] = React.useState<AvatarState>({ image: null, preview: null });

  const set = React.useCallback((file: File | null, onError: (message: string) => void) => {
    if (!file) {
      setState({ image: null, preview: null });

      return;
    }

    if (!file.type.startsWith("image/")) {
      onError("That file is not an image.");

      return;
    }

    if (file.size > MAX_AVATAR_BYTES) {
      onError("That image is over 8 MB. Anything that large is wasted on an avatar.");

      return;
    }

    const reader = new FileReader();

    reader.onload = () => {
      const source = String(reader.result);
      const image = new Image();

      image.onload = () => setState({ image, preview: source });
      image.onerror = () => onError("That image could not be read.");
      image.src = source;
    };

    reader.onerror = () => onError("That image could not be read.");
    reader.readAsDataURL(file);
  }, []);

  return [state, set];
}

/** Click or drop. Both paths end in the same `File`, so both are one handler. */
function AvatarPicker({
  state,
  onPick,
}: {
  state: AvatarState;
  onPick: (file: File | null) => void;
}) {
  const inputRef = React.useRef<HTMLInputElement>(null);
  const [over, setOver] = React.useState(false);

  return (
    <div className="flex min-w-0 flex-col gap-1.5 self-end">
      <span className="text-sm font-medium text-[var(--color-foreground)]">Avatar</span>

      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          onDragOver={(event) => {
            event.preventDefault();
            setOver(true);
          }}
          onDragLeave={() => setOver(false)}
          onDrop={(event) => {
            event.preventDefault();
            setOver(false);
            onPick(event.dataTransfer.files[0] ?? null);
          }}
          aria-label={state.preview ? "Replace avatar" : "Add an avatar"}
          className={cn(
            "flex size-11 items-center justify-center overflow-hidden rounded-[var(--radius-md)]",
            "border border-dashed border-[var(--color-border-strong)] text-[var(--color-foreground-subtle)]",
            "transition-colors hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]",
            over && "border-[var(--color-primary)] bg-[var(--color-primary-subtle)]",
          )}
        >
          {state.preview ? (
            // eslint-disable-next-line @next/next/no-img-element -- a local data URL; there is nothing for the image optimiser to fetch or cache.
            <img src={state.preview} alt="" className="size-full object-cover" />
          ) : (
            <ImagePlus className="size-4" aria-hidden="true" />
          )}
        </button>

        {state.preview && (
          <button
            type="button"
            onClick={() => onPick(null)}
            className="text-[var(--color-foreground-subtle)] transition-colors hover:text-[var(--color-danger)]"
            aria-label="Remove avatar"
          >
            <Trash2 className="size-4" aria-hidden="true" />
          </button>
        )}

        <span className="text-xs text-[var(--color-foreground-subtle)]">Never uploaded</span>
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="sr-only"
        onChange={(event) => onPick(event.target.files?.[0] ?? null)}
      />
    </div>
  );
}
