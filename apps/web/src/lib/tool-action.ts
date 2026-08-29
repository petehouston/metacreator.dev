/**
 * The verb a tool performs, for its primary button.
 *
 * "Run tool" is what a button says when nobody decided what the button does. A
 * splitter should say "Split"; an analyzer should say "Analyze". Naming the action
 * is worth real conversion on a page whose entire job is to get one click.
 *
 * This is derived rather than stored: tool names in the catalog are already built
 * from agent nouns ("Headline & Title Analyzer", "X Thread Splitter"), so the noun
 * *is* the verb, one suffix away. A new tool therefore gets a correct button label
 * the moment it is seeded, with no migration and no frontend change.
 *
 * `OVERRIDES` is the escape hatch for the handful of names the rule cannot reach.
 */

/**
 * Agent-noun suffix → verb. Longest suffix wins, so `-writer` beats `-riter`.
 *
 * `creator` is deliberately absent: on this site it is a descriptor in half the
 * copy ("creator tools", "Creator Media Kit"), so matching it would hand out
 * "Create" to tools that do nothing of the sort. `maker` and `builder` cover the
 * cases where making really is the action.
 */
const SUFFIX_VERBS: Record<string, string> = {
  analyzer: "Analyze",
  analyser: "Analyse",
  generator: "Generate",
  calculator: "Calculate",
  converter: "Convert",
  translator: "Translate",
  downloader: "Download",
  uploader: "Upload",
  splitter: "Split",
  merger: "Merge",
  joiner: "Join",
  counter: "Count",
  builder: "Build",
  maker: "Make",
  writer: "Write",
  rewriter: "Rewrite",
  editor: "Edit",
  formatter: "Format",
  cleaner: "Clean",
  compressor: "Compress",
  optimizer: "Optimize",
  optimiser: "Optimise",
  resizer: "Resize",
  cropper: "Crop",
  extractor: "Extract",
  parser: "Parse",
  scraper: "Scrape",
  checker: "Check",
  validator: "Validate",
  tester: "Test",
  scanner: "Scan",
  detector: "Detect",
  finder: "Find",
  searcher: "Search",
  picker: "Pick",
  selector: "Select",
  sorter: "Sort",
  ranker: "Rank",
  scorer: "Score",
  rater: "Rate",
  grader: "Grade",
  auditor: "Audit",
  reviewer: "Review",
  comparer: "Compare",
  comparator: "Compare",
  estimator: "Estimate",
  predictor: "Predict",
  forecaster: "Forecast",
  tracker: "Track",
  monitor: "Monitor",
  planner: "Plan",
  scheduler: "Schedule",
  organizer: "Organize",
  summarizer: "Summarize",
  summariser: "Summarise",
  transcriber: "Transcribe",
  captioner: "Caption",
  tagger: "Tag",
  labeller: "Label",
  namer: "Name",
  suggester: "Suggest",
  recommender: "Recommend",
  encoder: "Encode",
  decoder: "Decode",
  encrypter: "Encrypt",
  hasher: "Hash",
  previewer: "Preview",
  simulator: "Simulate",
  renderer: "Render",
  designer: "Design",
  trimmer: "Trim",
  padder: "Pad",
  filter: "Filter",
  grouper: "Group",
  mixer: "Mix",
  shortener: "Shorten",
  expander: "Expand",
  sizer: "Size",
  viewer: "View",
  timer: "Time",
};

/** Bare nouns that are the action, with no agent suffix to strip. */
const WORD_VERBS: Record<string, string> = {
  analysis: "Analyze",
  audit: "Audit",
  benchmark: "Benchmark",
  calculator: "Calculate",
  comparison: "Compare",
  converter: "Convert",
  download: "Download",
  generator: "Generate",
  ideas: "Generate ideas",
  preview: "Preview",
  report: "Generate report",
  score: "Score",
  stats: "Get stats",
  suggestions: "Get suggestions",
  summary: "Summarize",
  tips: "Get tips",
};

/** Names the rule gets wrong, or where a better verb exists. Keyed by slug. */
const OVERRIDES: Record<string, string> = {
  "giveaway-winner-picker": "Pick a winner",
  "utm-link-builder": "Build the link",
  "social-media-character-counter": "Count characters",
  "youtube-thumbnail-downloader": "Get the thumbnail",
  "word-counter": "Count words",
  "script-timer": "Time the script",
  "link-preview-debugger": "Preview the URL",
  "follower-milestone-countdown": "Check followers",
  "safe-zone-guide": "Show safe zones",
  "story-templates-sizer": "Size for Stories",
  "pin-image-sizer": "Size the pin",
  "youtube-metadata-viewer": "View metadata",
  "youtube-content-calendar": "Build the calendar",
  "youtube-search-suggestions": "Get suggestions",
};

/**
 * Returns the imperative verb for a tool's primary button — "Generate", "Split",
 * "Analyze". Falls back to "Run tool" only when nothing in the name is a verb.
 */
export function actionVerb(name: string, slug?: string): string {
  if (slug && OVERRIDES[slug]) return OVERRIDES[slug];

  // Walk the name from the last word back: the head noun of "Headline & Title
  // Analyzer" is the last one, and that is the word carrying the action.
  const words = name
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, " ")
    .split(/[\s-]+/)
    .filter(Boolean);

  for (let index = words.length - 1; index >= 0; index -= 1) {
    const word = words[index];

    if (WORD_VERBS[word]) return WORD_VERBS[word];

    // Longest suffix first, so `summarizer` is not matched as `riser`.
    const suffix = Object.keys(SUFFIX_VERBS)
      .filter((candidate) => word.endsWith(candidate))
      .sort((a, b) => b.length - a.length)[0];

    if (suffix) return SUFFIX_VERBS[suffix];
  }

  return "Run tool";
}

/** The present-participle form, for the pending state: "Analyzing…". */
export function actionPending(name: string, slug?: string): string {
  const verb = actionVerb(name, slug);

  if (verb === "Run tool") return "Running…";

  // Only the leading word inflects — "Pick a winner" becomes "Picking a winner".
  const [head, ...rest] = verb.split(" ");
  const tail = rest.length > 0 ? ` ${rest.join(" ")}` : "";

  return `${inflect(head)}${tail}…`;
}

function inflect(verb: string): string {
  const lower = verb.toLowerCase();

  // Consonant + silent -e drops the e: Analyze → Analyzing, Rate → Rating.
  if (lower.endsWith("e") && !lower.endsWith("ee") && !lower.endsWith("ye")) {
    return `${verb.slice(0, -1)}ing`;
  }

  // Single-syllable CVC doubles the final consonant: Trim → Trimming.
  if (/^[^aeiou]*[aeiou][^aeiouwxy]$/.test(lower)) {
    return `${verb}${verb.slice(-1)}ing`;
  }

  return `${verb}ing`;
}
