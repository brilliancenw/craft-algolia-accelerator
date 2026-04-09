# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-04-09

### Added

- Initial release
- **Cloudflare Worker** with KV-backed response caching
  - FNV-1a hash-based cache keys for deterministic lookups
  - Configurable cache TTL (default 24 hours)
  - Token-authenticated cache purge endpoint
  - CORS support for browser requests
  - Cache bypass via `?nocache=true` query parameter
  - Response headers: `X-Cache`, `X-Cache-Key`, `X-Response-Time`
  - Health check endpoint at `/health`
- **Craft CMS Plugin** for automatic cache management
  - Event-driven cache purging on Entry save
  - Configurable section-to-trigger mapping
  - Control Panel settings with step-by-step Cloudflare setup guide
  - Utility page for manual cache purging and status monitoring
  - Single purge per request optimization
  - Graceful degradation if worker unavailable
- **Frontend Integration** documentation
  - InstantSearch.js examples
  - Autocomplete.js examples
  - Direct API call examples
- **Troubleshooting** section in utility for cache HIT/MISS debugging
