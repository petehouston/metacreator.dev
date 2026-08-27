"use client";

import { Bold, Code, Italic, Link2, Strikethrough } from "lucide-react";
import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * A contentEditable field that stores HTML.
 *
 * Two decisions worth stating, because both look like bugs until you know why:
 *
 * **`innerHTML` is written only when the value came from outside.** React cannot own
 * a contentEditable's children — re-rendering it on every keystroke destroys and
 * rebuilds the DOM the caret lives in, and the cursor jumps to the start. So the
 * element is written to imperatively, and only when the incoming value differs from
 * what is already in the DOM.
 *
 * **Formatting uses `document.execCommand`.** It is deprecated and it is also the
 * only API that applies inline formatting to a selection with the browser's own
 * undo stack intact. The alternative is a Selection/Range implementation that gets
 * nested-tag merging wrong in ways nobody notices until an editor loses a word.
 * The output is sanitised server-side on save regardless (docs/21), which is what
 * makes relying on it safe.
 */
export function RichText({
  value,
  onChange,
  placeholder,
  className,
  multiline = true,
  ariaLabel,
  onEnterAtEnd,
  onBackspaceWhenEmpty,
}: {
  value: string;
  onChange: (html: string) => void;
  placeholder?: string;
  className?: string;
  /** Single-line fields swallow Enter so it can create the next block instead. */
  multiline?: boolean;
  ariaLabel?: string;
  onEnterAtEnd?: () => void;
  onBackspaceWhenEmpty?: () => void;
}) {
  const ref = React.useRef<HTMLDivElement>(null);
  const [toolbar, setToolbar] = React.useState<{ top: number; left: number } | null>(null);

  React.useEffect(() => {
    const element = ref.current;

    if (element && element.innerHTML !== value) {
      element.innerHTML = value;
    }
  }, [value]);

  function emit() {
    onChange(ref.current?.innerHTML ?? "");
  }

  function positionToolbar() {
    const selection = window.getSelection();

    if (
      !selection ||
      selection.isCollapsed ||
      !ref.current?.contains(selection.anchorNode)
    ) {
      setToolbar(null);
      return;
    }

    const rect = selection.getRangeAt(0).getBoundingClientRect();
    const host = ref.current.getBoundingClientRect();

    setToolbar({ top: rect.top - host.top - 42, left: rect.left - host.left });
  }

  function format(command: string) {
    // Restore focus first: clicking the toolbar moved it, and execCommand acts on
    // the focused element's selection.
    ref.current?.focus();
    document.execCommand(command);
    emit();
  }

  function addLink() {
    const url = window.prompt("Link to where?");

    if (url === null || url.trim() === "") return;

    ref.current?.focus();
    document.execCommand("createLink", false, url.trim());
    emit();
  }

  return (
    <div className="relative">
      {toolbar && (
        <div
          className="absolute z-20 flex items-center gap-0.5 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--app-surface)] p-1 shadow-[var(--shadow-popover)]"
          style={{ top: toolbar.top, left: toolbar.left }}
          // The toolbar must not steal the selection it is about to act on.
          onMouseDown={(event) => event.preventDefault()}
        >
          <FormatButton label="Bold" icon={Bold} onClick={() => format("bold")} />
          <FormatButton label="Italic" icon={Italic} onClick={() => format("italic")} />
          <FormatButton
            label="Strikethrough"
            icon={Strikethrough}
            onClick={() => format("strikeThrough")}
          />
          <FormatButton label="Inline code" icon={Code} onClick={() => wrapCode(ref, emit)} />
          <FormatButton label="Link" icon={Link2} onClick={addLink} />
        </div>
      )}

      <div
        ref={ref}
        contentEditable
        suppressContentEditableWarning
        role="textbox"
        aria-multiline={multiline}
        aria-label={ariaLabel ?? placeholder}
        data-placeholder={placeholder}
        onInput={emit}
        onBlur={emit}
        onMouseUp={positionToolbar}
        onKeyUp={positionToolbar}
        onKeyDown={(event) => {
          if (event.key === "Enter" && !event.shiftKey) {
            if (!multiline) {
              event.preventDefault();
              onEnterAtEnd?.();
              return;
            }

            // At the very end of a full block, Enter starts the next one — the
            // behaviour a Notion-shaped editor is expected to have.
            if (onEnterAtEnd && isCaretAtEnd(ref.current)) {
              event.preventDefault();
              onEnterAtEnd();
            }
          }

          if (
            event.key === "Backspace" &&
            onBackspaceWhenEmpty &&
            (ref.current?.textContent ?? "") === ""
          ) {
            event.preventDefault();
            onBackspaceWhenEmpty();
          }
        }}
        onPaste={(event) => {
          // Paste as plain text. Pasting from Word or a webpage otherwise drags in
          // font tags and inline styles that the sanitiser strips on save — so the
          // editor would show formatting the published article does not have.
          event.preventDefault();
          const text = event.clipboardData.getData("text/plain");
          document.execCommand("insertText", false, text);
          emit();
        }}
        className={cn(
          "min-h-[1.5em] w-full outline-none",
          "empty:before:pointer-events-none empty:before:text-[var(--color-foreground-subtle)] empty:before:content-[attr(data-placeholder)]",
          "focus:outline-none",
          "[&_a]:text-[var(--color-primary)] [&_a]:underline [&_a]:underline-offset-2",
          "[&_code]:rounded [&_code]:bg-[var(--color-surface-sunken)] [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.9em]",
          "[&_strong]:font-semibold [&_strong]:text-[var(--color-foreground)]",
          className,
        )}
      />
    </div>
  );
}

function FormatButton({
  label,
  icon: Icon,
  onClick,
}: {
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      title={label}
      className="flex size-7 items-center justify-center rounded-[var(--radius-sm)] text-[var(--color-foreground-muted)] transition-colors hover:bg-[var(--color-surface-sunken)] hover:text-[var(--color-foreground)]"
    >
      <Icon className="size-3.5" aria-hidden="true" />
    </button>
  );
}

/**
 * `execCommand` has no "inline code", so the selection is wrapped by hand.
 *
 * Deliberately does nothing to an already-wrapped selection rather than nesting a
 * second `<code>`: the sanitiser would collapse it anyway, and the editor showing
 * something the article will not have is the thing this file exists to avoid.
 */
function wrapCode(ref: React.RefObject<HTMLDivElement | null>, emit: () => void) {
  ref.current?.focus();

  const selection = window.getSelection();

  if (!selection || selection.isCollapsed) return;

  const range = selection.getRangeAt(0);

  if ((range.commonAncestorContainer.parentElement?.closest("code") ?? null) !== null) {
    return;
  }

  const code = document.createElement("code");

  try {
    range.surroundContents(code);
  } catch {
    // `surroundContents` throws when the selection crosses an element boundary.
    // Falling back to the text content loses nested formatting, which is the
    // correct trade for a code span.
    code.textContent = range.toString();
    range.deleteContents();
    range.insertNode(code);
  }

  selection.removeAllRanges();
  emit();
}

function isCaretAtEnd(element: HTMLElement | null): boolean {
  const selection = window.getSelection();

  if (!element || !selection || selection.rangeCount === 0) return false;

  const range = selection.getRangeAt(0).cloneRange();
  range.selectNodeContents(element);
  range.setStart(selection.anchorNode!, selection.anchorOffset);

  return range.toString().length === 0;
}
