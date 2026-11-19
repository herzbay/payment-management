import { NextRequest, NextResponse } from "next/server";

const BACKEND_URL = (
  process.env.BACKEND_URL || "http://127.0.0.1:8000"
).replace(/\/+$/, "");

export async function POST(request: NextRequest) {
  const token = request.cookies.get("pms_token")?.value;

  if (token) {
    try {
      await fetch(`${BACKEND_URL}/api/logout`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        cache: "no-store",
      });
    } catch {
      // Cookie tetap dihapus meskipun backend tidak dapat dihubungi.
    }
  }

  const response = NextResponse.json({
    message: "Logout berhasil.",
  });

  response.cookies.delete("pms_token");

  return response;
}
