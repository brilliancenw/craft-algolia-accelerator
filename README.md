# Craft Algolia Accelerator

A Cloudflare-powered caching layer for Algolia search with automatic cache purging.

## Overview

Algolia Accelerator consists of two components:

1. **Cloudflare Worker** - A caching proxy that sits between your frontend and Algolia, storing search responses in Cloudflare KV
2. **Craft CMS Plugin** - Automatically purges the cache when content is saved in configured sections

```
                        SEARCH FLOW
┌─────────────┐    ┌───────────────────┐    ┌─────────────┐
│   Browser   │───▶│    Cloudflare     │───▶│   Algolia   │
│  (Frontend) │◀───│  Worker + Cache   │◀───│     API     │
└─────────────┘    └───────────────────┘    └─────────────┘
                           ▲
                           │ cache purge
                   ┌───────┴───────┐
                   │   Craft CMS   │
                   │  (on save)    │
                   └───────────────┘
```

## Requirements

- PHP 8.2+
- Craft CMS 5.0+
- Cloudflare account (free tier works) with your domain's DNS routed through Cloudflare
- Algolia account with API credentials

## Installation

### Step 1: Install Craft Plugin

**From the Plugin Store (recommended):**

Search for "Craft Algolia Accelerator" in the Craft Plugin Store, or visit:
https://plugins.craftcms.com/algolia-accelerator

**Via Composer:**

```bash
composer require brilliancenw/craft-algolia-accelerator
php craft plugin/install algolia-accelerator
```

### Step 2: Deploy Cloudflare Worker

The plugin settings page includes a complete step-by-step guide for deploying the Cloudflare Worker through the Cloudflare Dashboard (no CLI required).

1. Go to **Settings** > **Plugins** > **Craft Algolia Accelerator**
2. Follow the "Step-by-Step Cloudflare Setup" guide

### Step 3: Configure the Plugin

1. Enter your **Worker Host** (the custom domain or workers.dev URL)
2. Enter your **Purge Token** (the secret you created in Cloudflare)
3. Select which **sections** should trigger cache purges when entries are saved
4. Enable the plugin

## Frontend Integration

Update your Algolia client to point to the Cloudflare Worker instead of Algolia directly:

### JavaScript (InstantSearch.js)

```javascript
// Instead of connecting directly to Algolia:
// const searchClient = algoliasearch('YOUR-APP-ID', 'YOUR-SEARCH-API-KEY');

// Connect through the cache worker:
const searchClient = {
  search(requests) {
    return fetch('https://search-api.yourdomain.com/1/indexes/*/queries', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ requests }),
    }).then(res => res.json());
  },
};

// Use with InstantSearch
const search = instantsearch({
  indexName: 'your_index_name',
  searchClient,
});
```

### Bypass Cache

To bypass the cache for testing or debugging, add `?nocache=true`:

```javascript
fetch('https://search-api.yourdomain.com/1/indexes/*/queries?nocache=true', {
  // ...
});
```

## Utility Page

The plugin includes a utility page for manual cache management:

1. Go to **Utilities** > **Algolia Accelerator**
2. View cache worker status and configuration
3. Click **Purge Cache** to manually clear all cached responses
4. Check troubleshooting tips for debugging cache HIT/MISS

## Response Headers

The worker adds these headers to all responses:

| Header | Description |
|--------|-------------|
| `X-Cache` | `HIT` if served from cache, `MISS` if fetched from Algolia |
| `X-Cache-Key` | The cache key used (for debugging) |
| `X-Response-Time` | Time taken to process the request |

## Configuration Reference

### Environment Variables (optional)

You can configure the plugin via environment variables:

```env
ALGOLIA_CACHE_WORKER_HOST="search-api.yourdomain.com"
ALGOLIA_CACHE_PURGE_TOKEN="your-secure-purge-token"
```

### Plugin Settings

| Setting | Description | Default |
|---------|-------------|---------|
| `enabled` | Enable/disable automatic cache purging | `true` |
| `workerHost` | Cloudflare Worker hostname | - |
| `purgeToken` | Cache purge authentication token | - |
| `triggerSections` | Sections that trigger cache purge on save | `[]` |

### Worker Environment Variables

Configure these in Cloudflare Worker settings:

| Variable | Description | Required |
|----------|-------------|----------|
| `ALGOLIA_HOST` | Algolia API host (e.g., `APP-ID-dsn.algolia.net`) | Yes |
| `ALGOLIA_APP_ID` | Algolia Application ID | Yes |
| `ALGOLIA_API_KEY` | Algolia Search API Key (secret) | Yes |
| `PURGE_TOKEN` | Token for authenticating purge requests (secret) | Yes |
| `CACHE_TTL_SECONDS` | Cache TTL in seconds | No (default: 86400) |

## Troubleshooting

### Cache not working

1. Verify the worker is deployed: visit `https://your-worker.domain/health`
2. Check that `X-Cache` header is present in responses
3. Ensure you're making identical requests (same query, params, etc.)

### Purge not working

1. Check Craft logs for purge errors: `storage/logs/web.log`
2. Verify Worker Host and Purge Token are set correctly
3. Ensure the entry's section is configured in plugin settings
4. Test manual purge from the Utility page

### CORS errors

The worker includes CORS headers by default. If you're having issues:

1. Check browser console for specific CORS error
2. Verify the worker is accessible from your domain
3. Check that your custom domain is properly configured

## Support

- **Documentation**: https://github.com/brilliancenw/craft-algolia-accelerator
- **Issues**: https://github.com/brilliancenw/craft-algolia-accelerator/issues
- **Email**: support@brilliancenw.com

## License

This plugin requires a license for production use. See [LICENSE.md](LICENSE.md) for details.

- **Price**: $49 (includes 1 year of updates and support)
- **Renewal**: $9/year for continued updates and support
