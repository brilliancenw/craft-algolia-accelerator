/**
 * Algolia Cache Worker
 *
 * Cloudflare Worker that acts as a caching proxy for Algolia search requests.
 * Caches search responses in Cloudflare KV with a configurable TTL to reduce
 * API costs and dramatically improve performance.
 *
 * Response times: ~300-500ms (cache miss) -> ~2-10ms (cache hit)
 */

export interface Env {
  SEARCH_CACHE: KVNamespace;
  ALGOLIA_HOST: string;
  ALGOLIA_APP_ID: string;
  ALGOLIA_API_KEY: string;
  PURGE_TOKEN?: string;
  CACHE_TTL_SECONDS?: string;
  ENVIRONMENT?: string;
}

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
  "Access-Control-Allow-Headers": "Content-Type, x-algolia-api-key, x-algolia-application-id"
};

/**
 * Generate a cache key using FNV-1a hash
 * Includes pathname to differentiate between different Algolia endpoints
 */
function generateCacheKey(requestBody: unknown, pathname: string): string {
  function sortObject(obj: unknown): unknown {
    if (obj === null || typeof obj !== "object") return obj;
    if (Array.isArray(obj)) return obj.map(sortObject);
    return Object.keys(obj as Record<string, unknown>).sort().reduce((acc: Record<string, unknown>, key: string) => {
      acc[key] = sortObject((obj as Record<string, unknown>)[key]);
      return acc;
    }, {});
  }

  const normalized = pathname + JSON.stringify(sortObject(requestBody));
  let hash = 2166136261;
  for (let i = 0; i < normalized.length; i++) {
    hash ^= normalized.charCodeAt(i);
    hash = Math.imul(hash, 16777619);
  }
  return `search:${(hash >>> 0).toString(16)}`;
}

/**
 * Forward request to Algolia API
 * Preserves the original pathname to support all Algolia endpoints
 */
async function fetchFromAlgolia(requestBody: unknown, env: Env, pathname: string): Promise<unknown> {
  const algoliaUrl = `https://${env.ALGOLIA_HOST}${pathname}`;
  const response = await fetch(algoliaUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "x-algolia-api-key": env.ALGOLIA_API_KEY,
      "x-algolia-application-id": env.ALGOLIA_APP_ID
    },
    body: JSON.stringify(requestBody)
  });
  if (!response.ok) {
    throw new Error(`Algolia error: ${response.status} ${response.statusText}`);
  }
  return response.json();
}

/**
 * Purge all cached search entries
 */
async function purgeCache(env: Env, ctx: ExecutionContext): Promise<{ success: boolean; deletedCount?: number; message?: string }> {
  if (!env.SEARCH_CACHE) {
    return { success: false, message: "Cache not configured" };
  }
  try {
    const keys = await env.SEARCH_CACHE.list({ prefix: "search:" });
    let deletedCount = 0;
    for (const key of keys.keys) {
      ctx.waitUntil(env.SEARCH_CACHE.delete(key.name));
      deletedCount++;
    }
    let cursor = keys.cursor;
    while (cursor) {
      const moreKeys = await env.SEARCH_CACHE.list({ prefix: "search:", cursor });
      for (const key of moreKeys.keys) {
        ctx.waitUntil(env.SEARCH_CACHE.delete(key.name));
        deletedCount++;
      }
      cursor = moreKeys.cursor;
    }
    console.log(JSON.stringify({
      timestamp: new Date().toISOString(),
      action: "cache_purge",
      deletedCount,
      environment: env.ENVIRONMENT || "unknown"
    }));
    return { success: true, deletedCount };
  } catch (error) {
    return { success: false, message: (error as Error).message };
  }
}

export default {
  async fetch(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
    const url = new URL(request.url);

    // Handle CORS preflight
    if (request.method === "OPTIONS") {
      return new Response(null, { headers: corsHeaders });
    }

    // Health check endpoint
    if (url.pathname === "/health") {
      return new Response(JSON.stringify({
        status: "ok",
        timestamp: new Date().toISOString(),
        environment: env.ENVIRONMENT || "unknown",
        cacheConfigured: !!env.SEARCH_CACHE
      }), {
        headers: { ...corsHeaders, "Content-Type": "application/json" }
      });
    }

    // Handle purge requests
    if (url.pathname === "/purge") {
      const authHeader = request.headers.get("Authorization");
      const purgeToken = env.PURGE_TOKEN;
      if (purgeToken && authHeader !== `Bearer ${purgeToken}`) {
        return new Response(JSON.stringify({ error: "Unauthorized" }), {
          status: 401,
          headers: { ...corsHeaders, "Content-Type": "application/json" }
        });
      }
      const result = await purgeCache(env, ctx);
      return new Response(JSON.stringify(result), {
        status: result.success ? 200 : 500,
        headers: { ...corsHeaders, "Content-Type": "application/json" }
      });
    }

    // Only allow POST for search requests
    if (request.method !== "POST") {
      return new Response("Method not allowed", {
        status: 405,
        headers: corsHeaders
      });
    }

    const startTime = Date.now();
    const bypassCache = url.searchParams.get("nocache") === "true";

    try {
      const requestBody = await request.json();
      const cacheKey = generateCacheKey(requestBody, url.pathname);

      // Extract search info for logging
      const searchInfo = {
        queries: (requestBody as { requests?: unknown[] }).requests?.length || 0,
        firstQuery: (requestBody as { requests?: Array<{ query?: string }>; query?: string }).requests?.[0]?.query || (requestBody as { query?: string }).query || "",
        indexName: (requestBody as { requests?: Array<{ indexName?: string }> }).requests?.[0]?.indexName || ""
      };

      let responseData: unknown;
      let cacheHit = false;

      // Check cache first (unless bypassed)
      if (!bypassCache && env.SEARCH_CACHE) {
        const cached = await env.SEARCH_CACHE.get(cacheKey, "json");
        if (cached) {
          responseData = cached;
          cacheHit = true;
        }
      }

      // Fetch from Algolia if not cached
      if (!responseData) {
        responseData = await fetchFromAlgolia(requestBody, env, url.pathname);
        if (env.SEARCH_CACHE && !bypassCache) {
          const ttl = parseInt(env.CACHE_TTL_SECONDS || "86400") || 86400;
          ctx.waitUntil(
            env.SEARCH_CACHE.put(cacheKey, JSON.stringify(responseData), {
              expirationTtl: ttl
            })
          );
        }
      }

      const duration = Date.now() - startTime;

      // Log request details
      console.log(JSON.stringify({
        timestamp: new Date().toISOString(),
        cacheHit,
        duration,
        cacheKey,
        pathname: url.pathname,
        query: searchInfo.firstQuery,
        indexName: searchInfo.indexName,
        queryCount: searchInfo.queries,
        environment: env.ENVIRONMENT || "unknown",
        userAgent: request.headers.get("user-agent")?.substring(0, 100)
      }));

      return new Response(JSON.stringify(responseData), {
        headers: {
          ...corsHeaders,
          "Content-Type": "application/json",
          "X-Cache": cacheHit ? "HIT" : "MISS",
          "X-Cache-Key": cacheKey,
          "X-Response-Time": `${duration}ms`
        }
      });
    } catch (error) {
      console.error("Worker error:", (error as Error).message);
      return new Response(JSON.stringify({
        error: "Search request failed",
        message: (error as Error).message
      }), {
        status: 500,
        headers: {
          ...corsHeaders,
          "Content-Type": "application/json"
        }
      });
    }
  }
};
