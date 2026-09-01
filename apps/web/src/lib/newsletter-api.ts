"use client";

import { apiData } from "./http";

/**
 * The public newsletter endpoints.
 *
 * Routed through {@link apiData} rather than a bare `fetch` so the Sanctum CSRF
 * token is primed first — the API's stateful guard applies to every mutating
 * request from the first-party frontend, signup forms included.
 */

export interface SubscribeResult {
  subscribed: boolean;
  requires_confirmation: boolean;
}

export const newsletterApi = {
  subscribe: (payload: { email: string; source: string; source_url?: string }) =>
    apiData<SubscribeResult>("/newsletter/subscribe", { method: "POST", body: payload }),

  confirm: (token: string) =>
    apiData<{ confirmed: boolean; email: string }>("/newsletter/confirm", {
      method: "POST",
      body: { token },
    }),
};
