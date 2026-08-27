"use client";

import { apiData, apiFetch, type ApiResult } from "./http";
import type {
  AuthUser,
  NotificationItem,
  NotificationPreferenceGroup,
  Paginated,
  UserDevice,
} from "./types";

/**
 * Typed wrappers over the account endpoints.
 *
 * Thin on purpose — the value is that every call site names an endpoint once, so a
 * route rename is a compile error rather than a runtime 404 discovered in QA.
 */

export interface RegisterPayload {
  email: string;
  password: string;
  display_name?: string;
  marketing_opt_in?: boolean;
  timezone?: string;
}

export const authApi = {
  session: () => apiData<AuthUser | null>("/auth/session"),

  register: (payload: RegisterPayload) =>
    apiData<AuthUser>("/auth/register", { method: "POST", body: payload }),

  login: (email: string, password: string, remember = true) =>
    apiData<AuthUser>("/auth/login", {
      method: "POST",
      body: { email, password, remember },
    }),

  logout: () => apiFetch<unknown>("/auth/logout", { method: "POST" }),

  requestMagicLink: (email: string, redirectTo?: string) =>
    apiData<{ sent: boolean; message: string }>("/auth/magic-link", {
      method: "POST",
      body: { email, redirect_to: redirectTo },
    }),

  consumeMagicLink: (token: string) =>
    apiFetch<{ data: AuthUser; meta: { redirect_to: string | null } }>(
      "/auth/magic-link/consume",
      { method: "POST", body: { token } },
    ),

  forgotPassword: (email: string) =>
    apiData<{ sent: boolean; message: string }>("/auth/password/forgot", {
      method: "POST",
      body: { email },
    }),

  resetPassword: (payload: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => apiData<{ reset: boolean; message: string }>("/auth/password/reset", {
    method: "POST",
    body: payload,
  }),

  confirmPassword: (password: string) =>
    apiData<{ confirmed: boolean }>("/auth/password/confirm", {
      method: "POST",
      body: { password },
    }),

  resendVerification: () =>
    apiData<{ verified: boolean; sent: boolean }>("/auth/email/resend", { method: "POST" }),

  /**
   * The verification link is a signed *absolute* API URL that arrived by email, so
   * it is fetched as-is rather than composed from a path.
   */
  verifyEmail: async (signedUrl: string): Promise<ApiResult<{ verified: boolean }>> => {
    try {
      const response = await fetch(signedUrl, {
        credentials: "include",
        headers: { Accept: "application/json" },
      });
      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        return {
          ok: false,
          error: {
            code: payload?.error?.code ?? "server.error",
            message:
              payload?.error?.message ??
              "That confirmation link is no longer valid. Ask for a new one.",
            status: response.status,
          },
        };
      }

      return { ok: true, data: payload.data };
    } catch {
      return {
        ok: false,
        error: { code: "network.unreachable", message: "We couldn't reach the server.", status: 0 },
      };
    }
  },
};

export const accountApi = {
  updateProfile: (payload: Partial<Pick<AuthUser, "display_name" | "timezone" | "locale" | "marketing_opt_in">>) =>
    apiData<AuthUser>("/account/profile", { method: "PATCH", body: payload }),

  changePassword: (payload: {
    current_password?: string;
    password: string;
    password_confirmation: string;
  }) => apiData<AuthUser>("/account/password", { method: "PATCH", body: payload }),

  uploadAvatar: (file: File) => {
    const formData = new FormData();
    formData.append("avatar", file);

    return apiData<AuthUser>("/account/avatar", { method: "POST", formData });
  },

  removeAvatar: () => apiData<AuthUser>("/account/avatar", { method: "DELETE" }),

  devices: () => apiFetch<Paginated<UserDevice>>("/account/devices"),

  revokeDevice: (id: number) =>
    apiData<{ revoked: boolean; was_current: boolean }>(`/account/devices/${id}`, {
      method: "DELETE",
    }),

  notificationPreferences: () =>
    apiData<NotificationPreferenceGroup[]>("/account/notification-preferences"),

  saveNotificationPreferences: (
    preferences: { event_key: string; email: boolean; in_app: boolean }[],
  ) =>
    apiData<NotificationPreferenceGroup[]>("/account/notification-preferences", {
      method: "PUT",
      body: { preferences },
    }),
};

export const notificationsApi = {
  list: (params: { page?: number; unread?: boolean; group?: string } = {}) =>
    apiFetch<Paginated<NotificationItem> & { meta: { unread: number } }>("/notifications", {
      searchParams: {
        page: params.page,
        "filter[unread]": params.unread ? 1 : undefined,
        "filter[group]": params.group,
      },
    }),

  markRead: (ids: string[]) =>
    apiData<{ marked: number; unread: number }>("/notifications/read", {
      method: "POST",
      body: { ids },
    }),

  markAllRead: () =>
    apiData<{ marked: number; unread: number }>("/notifications/read-all", { method: "POST" }),
};
