"use client";

import { Download, ImagePlus, Trash2 } from "lucide-react";
import * as React from "react";

import type { CustomToolProps } from "@/tools/custom";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/field";
import {
  AGO_UNITS,
  drawComment,
  type AgoUnit,
  type CommentCard,
  type CommentTheme,
  type Reaction,
} from "@/tools/custom/youtube-comment-card";
import { cn } from "@/lib/utils";

/**
 * The Fake YouTube Comment Generator.
 *
 * A custom UI rather than the generated form for one reason that matters: the card
 * is drawn on a canvas *here*, in the browser, so an avatar someone drops in is
 * read with `FileReader` and never sent anywhere. There is no upload, so there is
 * nothing for us to store, leak or have to promise to delete. The preview also has
 * to be live — nobody sets a like count and a theme blind and then submits a form
 * to find out what they got.
 */

const FORMATS = [
  { label: "PNG", mime: "image/png", extension: "png" },
  { label: "JPG", mime: "image/jpeg", extension: "jpg" },
  { label: "WebP", mime: "image/webp", extension: "webp" },
] as const;

const MAX_AVATAR_BYTES = 8 * 1024 * 1024;

interface FormState {
  username: string;
  content: string;
  time: string;
  unit: AgoUnit;
  likes: string;
  reaction: Reaction;
  creatorLiked: boolean;
  theme: CommentTheme;
  transparent: boolean;
}

const INITIAL: FormState = {
  username: "",
  content: "",
  time: "5",
  unit: "hours",
  likes: "0",
  reaction: "neutral",
  creatorLiked: false,
  theme: "light",
  transparent: false,
};

export default function YouTubeCommentGenerator({ tool }: CustomToolProps) {
  const example = tool.example?.input as Partial<Record<keyof FormState, unknown>> | undefined;

  const [form, setForm] = React.useState<FormState>(() => ({
    ...INITIAL,
    // The catalog's worked example is the placeholder text elsewhere on the site;
    // here it is the starting state, because an empty canvas teaches nothing.
    username: typeof example?.username === "string" ? example.username : INITIAL.username,
    content: typeof example?.content === "string" ? example.content : INITIAL.content,
  }));

  const [avatar, setAvatar] = useAvatar();
  const [creatorAvatar, setCreatorAvatar] = useAvatar();
  const [error, setError] = React.useState<string | null>(null);

  const canvasRef = React.useRef<HTMLCanvasElement>(null);

  const card: CommentCard = {
    username: form.username.trim() === "" ? "username" : form.username,
    content: form.content.trim() === "" ? "Your comment will appear here." : form.content,
    time: Number(form.time) || 1,
    unit: form.unit,
    likes: Number(form.likes) || 0,
    reaction: form.reaction,
    creatorLiked: form.creatorLiked,
    theme: form.theme,
    transparent: form.transparent,
    avatar: avatar.image,
    creatorAvatar: creatorAvatar.image,
  };

  // Redraw on every change. The card is a few hundred fill operations, so there is
  // nothing here worth debouncing — a keystroke-to-pixel delay would be felt.
  React.useEffect(() => {
    if (canvasRef.current) drawComment(canvasRef.current, card);
  });

  function update<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function download(format: (typeof FORMATS)[number]) {
    const canvas = canvasRef.current;

    if (!canvas) return;

    // JPEG has no alpha channel, and a canvas with a transparent background
    // encodes to *black* in one — which would hand someone a ruined card and no
    // explanation. Redraw opaque for the encode, then put the preview back.
    const opaque = format.mime === "image/jpeg" && form.transparent;

    if (opaque) drawComment(canvas, { ...card, transparent: false });

    canvas.toBlob(
      (blob) => {
        if (!blob) {
          if (opaque) drawComment(canvas, card);
          setError("Your browser could not produce that format. Try PNG.");
          return;
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = `youtube-comment-${form.theme}.${format.extension}`;
        link.click();

        // Revoked on the next tick rather than immediately: the click is
        // synchronous but the fetch the browser does for it is not.
        setTimeout(() => URL.revokeObjectURL(url), 1000);

        if (opaque) drawComment(canvas, card);
      },
      format.mime,
      format.mime === "image/png" ? undefined : 0.95,
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <section
        aria-labelledby="comment-input-heading"
        className="panel relative overflow-hidden p-5 shadow-[var(--shadow-card)] sm:p-7"
      >
        <span
          aria-hidden="true"
          className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[var(--color-primary)] to-transparent opacity-70"
        />

        <h2 id="comment-input-heading" className="sr-only">
          Comment details
        </h2>

        <div className="flex flex-col gap-8">
          <Group title="Commenter">
            <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_auto]">
              <Field id="yt-username" label="Username" required className="min-w-0">
                {(aria) => (
                  <Input
                    {...aria}
                    placeholder="John_Smith"
                    maxLength={60}
                    value={form.username}
                    onChange={(event) => update("username", event.target.value)}
                  />
                )}
              </Field>

              <AvatarPicker
                id="yt-avatar"
                label="Avatar"
                state={avatar}
                onPick={(file) => setAvatar(file, setError)}
              />
            </div>
          </Group>

          <Group title="Comment">
            <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_7rem_10rem]">
              <Field id="yt-content" label="Content" required className="min-w-0">
                {(aria) => (
                  <Textarea
                    {...aria}
                    rows={3}
                    className="min-h-24"
                    placeholder="This video was very funny, thanks for sharing"
                    maxLength={2000}
                    value={form.content}
                    onChange={(event) => update("content", event.target.value)}
                  />
                )}
              </Field>

              <Field id="yt-time" label="Time" required className="min-w-0">
                {(aria) => (
                  <Input
                    {...aria}
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={999}
                    value={form.time}
                    onChange={(event) => update("time", event.target.value)}
                  />
                )}
              </Field>

              <Field id="yt-unit" label="Ago" required className="min-w-0">
                {(aria) => (
                  <Select
                    {...aria}
                    value={form.unit}
                    onChange={(event) => update("unit", event.target.value as AgoUnit)}
                  >
                    {AGO_UNITS.map((unit) => (
                      <option key={unit} value={unit}>
                        {unit}
                      </option>
                    ))}
                  </Select>
                )}
              </Field>
            </div>
          </Group>

          <Group title="Interaction">
            <div className="grid gap-5 sm:grid-cols-[10rem_minmax(0,1fr)] lg:grid-cols-[10rem_minmax(0,1fr)_auto]">
              <Field id="yt-likes" label="Likes" className="min-w-0">
                {(aria) => (
                  <Input
                    {...aria}
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={99999999}
                    value={form.likes}
                    onChange={(event) => update("likes", event.target.value)}
                  />
                )}
              </Field>

              <fieldset className="min-w-0">
                <legend className="mb-1.5 text-sm font-medium text-[var(--color-foreground)]">
                  Commenter
                </legend>

                <div className="flex h-11 flex-wrap items-center gap-4">
                  {(["neutral", "like", "dislike"] as Reaction[]).map((option) => (
                    <label
                      key={option}
                      className="flex cursor-pointer items-center gap-2 text-sm text-[var(--color-foreground-muted)]"
                    >
                      <input
                        type="radio"
                        name="yt-reaction"
                        value={option}
                        checked={form.reaction === option}
                        onChange={() => update("reaction", option)}
                        className="size-4 accent-[var(--color-primary)]"
                      />
                      <span className="capitalize">{option}</span>
                    </label>
                  ))}
                </div>
              </fieldset>

              <AvatarPicker
                id="yt-creator-avatar"
                label="Creator avatar"
                state={creatorAvatar}
                disabled={!form.creatorLiked}
                onPick={(file) => setCreatorAvatar(file, setError)}
              />
            </div>

            <div className="mt-1 grid gap-2 sm:grid-cols-2">
              <Checkbox
                id="yt-creator-liked"
                label="Creator liked this comment"
                hint="Stamps the creator's heart into the action row."
                checked={form.creatorLiked}
                onChange={(event) => update("creatorLiked", event.target.checked)}
              />

              <Checkbox
                id="yt-transparent"
                label="Transparent background"
                hint="PNG and WebP only — a JPG is always drawn on the theme colour."
                checked={form.transparent}
                onChange={(event) => update("transparent", event.target.checked)}
              />
            </div>
          </Group>

          <Group title="Theme">
            <div
              role="radiogroup"
              aria-label="Theme"
              className="inline-flex rounded-[var(--radius-md)] border border-[var(--color-border)] p-1"
            >
              {(["light", "dark"] as CommentTheme[]).map((option) => (
                <button
                  key={option}
                  type="button"
                  role="radio"
                  aria-checked={form.theme === option}
                  onClick={() => update("theme", option)}
                  className={cn(
                    "rounded-[var(--radius-sm)] px-4 py-1.5 text-sm capitalize transition-colors",
                    form.theme === option
                      ? "bg-[var(--color-primary)] text-[var(--color-primary-foreground)]"
                      : "text-[var(--color-foreground-muted)] hover:text-[var(--color-foreground)]",
                  )}
                >
                  {option}
                </button>
              ))}
            </div>
          </Group>
        </div>

        {error && (
          <p role="alert" className="mt-5 text-sm font-medium text-[var(--color-danger)]">
            {error}
          </p>
        )}

        <p className="mt-6 text-xs text-[var(--color-foreground-subtle)]">
          Everything here is drawn in your browser. The images you add are never uploaded and
          nothing is stored — close the tab and they are gone.
        </p>
      </section>

      {/* ── The card, and the three ways to take it away. ─────────────────── */}
      <section aria-labelledby="comment-preview-heading" className="panel overflow-hidden shadow-[var(--shadow-card)]">
        <header className="border-b border-[var(--color-border-subtle)] px-5 py-3.5 sm:px-6">
          <h2 id="comment-preview-heading" className="text-heading-3">
            Preview
          </h2>
        </header>

        <div className="p-5 sm:p-6">
          <div
            className={cn(
              "overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border-subtle)]",
              // A transparent card needs something behind it or it is invisible on
              // whichever theme the site itself is in.
              form.transparent && "bg-[repeating-conic-gradient(var(--color-surface-sunken)_0_25%,transparent_0_50%)] bg-[length:20px_20px]",
            )}
          >
            <canvas
              ref={canvasRef}
              className="block w-full"
              role="img"
              aria-label={`YouTube comment by ${card.username}: ${card.content}`}
            />
          </div>

          <div className="mt-5 flex flex-wrap gap-3">
            {FORMATS.map((format) => (
              <Button key={format.mime} type="button" variant="secondary" onClick={() => download(format)}>
                <Download />
                Download {format.label}
              </Button>
            ))}
          </div>

          <p className="mt-4 text-xs text-[var(--color-foreground-subtle)]">
            This draws whatever you type, so it is a mock-up, not proof. Do not present a card as a
            screenshot of a comment someone actually left.
          </p>
        </div>
      </section>
    </div>
  );
}

function Group({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-4">
      <h3 className="eyebrow">{title}</h3>
      {children}
    </section>
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
      onError("That image is over 8 MB. Anything that large is wasted on an 80-pixel avatar.");
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
  id,
  label,
  state,
  disabled,
  onPick,
}: {
  id: string;
  label: string;
  state: AvatarState;
  disabled?: boolean;
  onPick: (file: File | null) => void;
}) {
  const inputRef = React.useRef<HTMLInputElement>(null);
  const [over, setOver] = React.useState(false);

  return (
    <div className={cn("flex flex-col gap-1.5", disabled && "opacity-50")}>
      <span className="text-sm font-medium text-[var(--color-foreground)]">{label}</span>

      <div className="flex items-center gap-2">
        <button
          type="button"
          disabled={disabled}
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
          aria-label={state.preview ? `Replace ${label.toLowerCase()}` : `Add ${label.toLowerCase()}`}
          className={cn(
            "flex size-11 items-center justify-center overflow-hidden rounded-[var(--radius-md)]",
            "border border-dashed border-[var(--color-border-strong)] text-[var(--color-foreground-subtle)]",
            "transition-colors hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]",
            "disabled:cursor-not-allowed",
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
            aria-label={`Remove ${label.toLowerCase()}`}
          >
            <Trash2 className="size-4" aria-hidden="true" />
          </button>
        )}
      </div>

      <input
        ref={inputRef}
        id={id}
        type="file"
        accept="image/*"
        className="sr-only"
        onChange={(event) => onPick(event.target.files?.[0] ?? null)}
      />
    </div>
  );
}
