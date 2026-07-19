/**
 * Cloudflare Worker + D1 Leaderboard Campaign Service
 *
 * Environment bindings needed:
 * - DB: D1Database
 */

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // CORS headers
    const corsHeaders = {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
      "Access-Control-Allow-Headers": "Content-Type",
    };

    if (request.method === "OPTIONS") {
      return new Response(null, { headers: corsHeaders });
    }

    try {
      // 1. GET /campaigns/active
      if (url.pathname === "/campaigns/active" && request.method === "GET") {
        const now = new Date().toISOString();
        const campaign = await env.DB.prepare(
          "SELECT * FROM campaigns WHERE opens_at <= ? AND closes_at >= ? LIMIT 1"
        )
          .bind(now, now)
          .first();

        return new Response(JSON.stringify(campaign || null), {
          headers: { "Content-Type": "application/json", ...corsHeaders },
        });
      }

      // 2. GET /leaderboard
      if (url.pathname === "/leaderboard" && request.method === "GET") {
        const campaignId = url.searchParams.get("campaign_id");
        if (!campaignId) {
          return new Response(JSON.stringify({ error: "Missing campaign_id" }), {
            status: 400,
            headers: { "Content-Type": "application/json", ...corsHeaders },
          });
        }

        // Get details of campaign
        const campaign = await env.DB.prepare(
          "SELECT closes_at FROM campaigns WHERE campaign_id = ?"
        )
          .bind(campaignId)
          .first();

        // Get entries
        const { results } = await env.DB.prepare(
          `SELECT display_name, domain_hint, blocked_percentage 
           FROM submissions 
           WHERE campaign_id = ? 
           ORDER BY blocked_percentage DESC, total_requests DESC`
        )
          .bind(campaignId)
          .all();

        const entries = results.map((r, i) => ({
          rank: i + 1,
          display_name: r.display_name,
          domain_hint: r.domain_hint,
          blocked_percentage: r.blocked_percentage,
        }));

        return new Response(
          JSON.stringify({
            campaign_id: campaignId,
            closes_at: campaign ? campaign.closes_at : null,
            entries,
          }),
          {
            headers: { "Content-Type": "application/json", ...corsHeaders },
          }
        );
      }

      // 3. POST /submit
      if (url.pathname === "/submit" && request.method === "POST") {
        const payload = await request.json();
        const {
          campaign_id,
          participant_id,
          period_start,
          period_end,
          total_requests,
          ai_bot_requests,
          blocked_percentage,
          display_name,
          domain_hint,
        } = payload;

        // Validation checks
        if (!campaign_id || !participant_id || !period_start || !period_end) {
          return new Response(JSON.stringify({ error: "Missing required fields" }), {
            status: 400,
            headers: { "Content-Type": "application/json", ...corsHeaders },
          });
        }

        // Check if campaign is active/open
        const nowStr = new Date().toISOString();
        const campaign = await env.DB.prepare(
          "SELECT * FROM campaigns WHERE campaign_id = ? AND opens_at <= ? AND closes_at >= ?"
        )
          .bind(campaign_id, nowStr, nowStr)
          .first();

        if (!campaign) {
          return new Response(JSON.stringify({ error: "Campaign is not currently open for submissions" }), {
            status: 400,
            headers: { "Content-Type": "application/json", ...corsHeaders },
          });
        }

        // Validate percentage bounds
        if (blocked_percentage < 0 || blocked_percentage > 100) {
          return new Response(JSON.stringify({ error: "Blocked percentage must be between 0 and 100" }), {
            status: 400,
            headers: { "Content-Type": "application/json", ...corsHeaders },
          });
        }

        // Math consistency: blocked_percentage must equal ai_bot_requests / total_requests * 100 within 0.5%
        if (total_requests > 0) {
          const calculatedPct = (ai_bot_requests / total_requests) * 100;
          if (Math.abs(calculatedPct - blocked_percentage) > 0.5) {
            return new Response(JSON.stringify({ error: "Mismatched mathematics on request counts" }), {
              status: 400,
              headers: { "Content-Type": "application/json", ...corsHeaders },
            });
          }
        }

        // Period duration check: end minus start must be exactly 7 days (604800 seconds)
        const startMs = new Date(period_start).getTime();
        const endMs = new Date(period_end).getTime();
        const diffSec = Math.round((endMs - startMs) / 1000);
        if (diffSec < 601200 || diffSec > 608400) { // Allow slight buffer (7 days is 604800s)
          return new Response(JSON.stringify({ error: "Report period must span exactly 7 days" }), {
            status: 400,
            headers: { "Content-Type": "application/json", ...corsHeaders },
          });
        }

        // Rate limiting: max 5 submissions per participant_id per hour
        const oneHourAgo = new Date(Date.now() - 3600000).toISOString();
        const countRes = await env.DB.prepare(
          "SELECT COUNT(*) as count FROM submissions WHERE participant_id = ? AND submitted_at >= ?"
        )
          .bind(participant_id, oneHourAgo)
          .first();

        if (countRes && countRes.count >= 5) {
          return new Response(JSON.stringify({ error: "Rate limit exceeded: max 5 submissions per hour" }), {
            status: 429,
            headers: { "Content-Type": "application/json", ...corsHeaders },
          });
        }

        // Insert or replace submission
        const displayNameClean = (display_name || "anonymous").trim().substring(0, 50);
        const domainHintClean = domain_hint ? domain_hint.trim().substring(0, 100) : null;

        await env.DB.prepare(
          `INSERT OR REPLACE INTO submissions (
            participant_id, campaign_id, period_start, period_end,
            total_requests, ai_bot_requests, blocked_percentage,
            display_name, domain_hint, submitted_at
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
        )
          .bind(
            participant_id,
            campaign_id,
            period_start,
            period_end,
            total_requests,
            ai_bot_requests,
            blocked_percentage,
            displayNameClean,
            domainHintClean,
            nowStr
          )
          .run();

        // Calculate rank
        const rankRes = await env.DB.prepare(
          `SELECT COUNT(*) as count 
           FROM submissions 
           WHERE campaign_id = ? AND blocked_percentage > ?`
        )
          .bind(campaign_id, blocked_percentage)
          .first();

        const totalRes = await env.DB.prepare(
          "SELECT COUNT(*) as count FROM submissions WHERE campaign_id = ?"
        )
          .bind(campaign_id)
          .first();

        return new Response(
          JSON.stringify({
            accepted: true,
            current_rank: (rankRes ? rankRes.count : 0) + 1,
            total_entries: totalRes ? totalRes.count : 0,
          }),
          {
            headers: { "Content-Type": "application/json", ...corsHeaders },
          }
        );
      }

      return new Response(JSON.stringify({ error: "Not Found" }), {
        status: 404,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });

    } catch (err) {
      return new Response(JSON.stringify({ error: err.message }), {
        status: 500,
        headers: { "Content-Type": "application/json", ...corsHeaders },
      });
    }
  },
};
