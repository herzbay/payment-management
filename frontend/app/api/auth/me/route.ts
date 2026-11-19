import { NextRequest, NextResponse } from "next/server";

const BACKEND_URL = (
  process.env.BACKEND_URL || "http://127.0.0.1:8000"
).replace(/\/+$/, "");

export async function GET(request: NextRequest) {
  const token = request.cookies.get("pms_token")?.value;

  if (!token) {
    return NextResponse.json(
      {
        message: "Anda belum login.",
      },
      { status: 401 }
    );
  }

  try {
    const backendResponse = await fetch(`${BACKEND_URL}/api/user`, {
      method: "GET",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
    });

    const payload = await backendResponse.json();

    const response = NextResponse.json(payload, {
      status: backendResponse.status,
    });

    if (backendResponse.status === 401) {
      response.cookies.delete("pms_token");
    }

    return response;
  } catch {
    return NextResponse.json(
      {
        message: "Tidak dapat mengambil profil pengguna.",
      },
      { status: 502 }
    );
  }
}
