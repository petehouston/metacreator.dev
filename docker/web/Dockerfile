# syntax=docker/dockerfile:1.7
FROM node:24-alpine AS base
RUN apk add --no-cache libc6-compat
WORKDIR /app

# ── Dev: source bind-mounted, node_modules in a named volume ──────────────────
FROM base AS dev
ENV NODE_ENV=development NEXT_TELEMETRY_DISABLED=1
COPY docker/web/dev-entrypoint.sh /usr/local/bin/dev-entrypoint
RUN chmod +x /usr/local/bin/dev-entrypoint
EXPOSE 3000
ENTRYPOINT ["dev-entrypoint"]
CMD ["npm", "run", "dev"]

# ── Dependencies ──────────────────────────────────────────────────────────────
FROM base AS deps
COPY apps/web/package.json apps/web/package-lock.json ./
RUN npm ci

# ── Build ─────────────────────────────────────────────────────────────────────
FROM base AS build
ENV NEXT_TELEMETRY_DISABLED=1
COPY --from=deps /app/node_modules ./node_modules
COPY apps/web/ ./
# Public env must be present at build time — Next inlines NEXT_PUBLIC_* values.
ARG NEXT_PUBLIC_APP_URL
ARG NEXT_PUBLIC_API_URL
RUN npm run build

# ── Production runtime (standalone output) ────────────────────────────────────
FROM base AS production
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1 PORT=3000
RUN addgroup -g 1001 -S nodejs && adduser -S nextjs -u 1001

COPY --from=build /app/public ./public
COPY --from=build --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=build --chown=nextjs:nodejs /app/.next/static ./.next/static

USER nextjs
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
    CMD wget -qO- http://127.0.0.1:3000/api/health || exit 1
CMD ["node", "server.js"]
