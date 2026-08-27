import {
  AlertCircle,
  Code2,
  FileQuestion,
  Heading2,
  Image as ImageIcon,
  Link2,
  List,
  Minus,
  MousePointerClick,
  Play,
  Quote,
  Table2,
  Type,
  Wrench,
  type LucideIcon,
} from "lucide-react";

import type { Block } from "@/lib/types";

/**
 * The editor's block registry.
 *
 * One entry per block type, and adding a type is one entry here plus one branch in
 * the editor's node switch — matching the backend, where it is one enum case and
 * one sanitiser branch. The registry is what makes "the editor component should be
 * extensible" true rather than aspirational.
 *
 * `defaults` matters more than it looks: a block inserted with the shape the
 * sanitiser expects round-trips unchanged, while one inserted with a missing key
 * silently loses whatever the editor put there instead.
 */
export interface BlockKind {
  type: string;
  label: string;
  /** Shown in the insert menu, and searched by the slash command. */
  description: string;
  icon: LucideIcon;
  keywords: string[];
  group: "Text" | "Media" | "Structure" | "Product";
  defaults: () => Record<string, unknown>;
  /** Text-shaped blocks get the caret and the inline formatting toolbar. */
  richText?: boolean;
}

export const BLOCK_KINDS: BlockKind[] = [
  {
    type: "paragraph",
    label: "Text",
    description: "Plain writing, with bold, links and inline code",
    icon: Type,
    keywords: ["p", "text", "paragraph", "write"],
    group: "Text",
    defaults: () => ({ html: "" }),
    richText: true,
  },
  {
    type: "heading",
    label: "Heading",
    description: "A section title — becomes an anchor in the article",
    icon: Heading2,
    keywords: ["h2", "h3", "title", "section"],
    group: "Text",
    defaults: () => ({ level: 2, text: "" }),
  },
  {
    type: "list",
    label: "List",
    description: "Bulleted, numbered or a checklist",
    icon: List,
    keywords: ["ul", "ol", "bullet", "number", "todo", "checklist"],
    group: "Text",
    defaults: () => ({ style: "unordered", items: [{ html: "", checked: false }] }),
  },
  {
    type: "quote",
    label: "Quote",
    description: "A pulled quotation with an attribution",
    icon: Quote,
    keywords: ["blockquote", "cite", "pull"],
    group: "Text",
    defaults: () => ({ text: "", cite: "", variant: "default" }),
    richText: true,
  },
  {
    type: "callout",
    label: "Callout",
    description: "A tip, warning or note that should not be missed",
    icon: AlertCircle,
    keywords: ["note", "tip", "warning", "danger", "info", "aside"],
    group: "Text",
    defaults: () => ({ tone: "info", title: "", html: "" }),
    richText: true,
  },
  {
    type: "image",
    label: "Image",
    description: "A picture, with the alt text it needs",
    icon: ImageIcon,
    keywords: ["picture", "photo", "img", "gif", "screenshot"],
    group: "Media",
    defaults: () => ({ url: "", alt: "", caption: "", size: "inline" }),
  },
  {
    type: "embed",
    label: "Embed",
    description: "YouTube, Vimeo, X, CodePen or any embeddable URL",
    icon: Play,
    keywords: ["video", "youtube", "vimeo", "twitter", "x", "tweet", "audio", "iframe"],
    group: "Media",
    defaults: () => ({ provider: "youtube", url: "", aspect: "16:9", caption: "" }),
  },
  {
    type: "code",
    label: "Code",
    description: "A syntax-labelled code block with a copy button",
    icon: Code2,
    keywords: ["snippet", "pre", "syntax"],
    group: "Media",
    defaults: () => ({ language: "text", filename: "", code: "" }),
  },
  {
    type: "html",
    label: "Custom HTML",
    description: "Raw markup — sanitised on save and again on render",
    icon: Link2,
    keywords: ["raw", "iframe", "script", "snippet"],
    group: "Media",
    defaults: () => ({ html: "" }),
  },
  {
    type: "table",
    label: "Table",
    description: "Rows and columns, with a header row",
    icon: Table2,
    keywords: ["grid", "rows", "columns", "comparison"],
    group: "Structure",
    defaults: () => ({
      rows: [
        ["", ""],
        ["", ""],
      ],
    }),
  },
  {
    type: "divider",
    label: "Divider",
    description: "A break between sections",
    icon: Minus,
    keywords: ["hr", "rule", "separator", "break"],
    group: "Structure",
    defaults: () => ({ style: "plain" }),
  },
  {
    type: "faq",
    label: "FAQ",
    description: "Questions and answers — emits FAQPage structured data",
    icon: FileQuestion,
    keywords: ["questions", "answers", "schema", "seo"],
    group: "Structure",
    defaults: () => ({ items: [{ question: "", answer: "" }] }),
  },
  {
    type: "button",
    label: "Button",
    description: "A call to action",
    icon: MousePointerClick,
    keywords: ["cta", "link", "action"],
    group: "Product",
    defaults: () => ({ label: "", href: "", variant: "primary" }),
  },
  {
    type: "toolCard",
    label: "Tool card",
    description: "Link a tool from the catalog, with its live tier and stats",
    icon: Wrench,
    keywords: ["tool", "catalog", "promote", "cross-link"],
    group: "Product",
    defaults: () => ({ toolSlug: "" }),
  },
];

export const BLOCK_KIND_BY_TYPE: Record<string, BlockKind> = Object.fromEntries(
  BLOCK_KINDS.map((kind) => [kind.type, kind]),
);

/** A new block, with an id the sanitiser will keep. */
export function makeBlock(type: string): Block {
  const kind = BLOCK_KIND_BY_TYPE[type];

  return {
    id: `b_${Math.random().toString(36).slice(2, 10)}${Date.now().toString(36)}`,
    type,
    data: kind ? kind.defaults() : {},
  };
}

export function searchKinds(query: string): BlockKind[] {
  const needle = query.trim().toLowerCase();

  if (needle === "") return BLOCK_KINDS;

  return BLOCK_KINDS.filter(
    (kind) =>
      kind.label.toLowerCase().includes(needle) ||
      kind.description.toLowerCase().includes(needle) ||
      kind.keywords.some((keyword) => keyword.includes(needle)),
  );
}
