/**
 * The handoff between the post editor and its preview tab.
 *
 * Session storage, so the preview shows the *unsaved* draft — previewing what is
 * already on the server would answer a question nobody asked. Its own module so the
 * preview page does not have to import the entire editor to learn one string.
 */
export const PREVIEW_STORAGE_KEY = "admin.post-preview";

/** The preview window name, so a second Preview reuses the same tab. */
export const PREVIEW_WINDOW = "post-preview";
