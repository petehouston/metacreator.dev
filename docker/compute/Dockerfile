# syntax=docker/dockerfile:1.7
FROM golang:1.24-alpine AS base
RUN apk add --no-cache git ffmpeg vips-tools
WORKDIR /app

# ── Dev: live reload via air ──────────────────────────────────────────────────
FROM base AS dev
RUN go install github.com/air-verse/air@v1.61.7
EXPOSE 8090
CMD ["air", "-c", ".air.toml"]

# ── Build ─────────────────────────────────────────────────────────────────────
FROM base AS build
COPY apps/compute/go.mod apps/compute/go.sum* ./
RUN go mod download
COPY apps/compute/ ./
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/compute ./cmd/compute

# ── Production runtime ────────────────────────────────────────────────────────
FROM alpine:3.20 AS production
RUN apk add --no-cache ca-certificates ffmpeg tzdata \
    && addgroup -S app && adduser -S app -G app
COPY --from=build /out/compute /usr/local/bin/compute
USER app
EXPOSE 8090
HEALTHCHECK --interval=30s --timeout=5s CMD wget -qO- http://127.0.0.1:8090/healthz || exit 1
ENTRYPOINT ["compute"]
