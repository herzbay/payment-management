import { NextRequest, NextResponse } from "next/server";

const BACKEND_URL = (
  process.env.BACKEND_URL || "http://127.0.0.1:8000"
).replace(/\/+$/, "");

interface LaravelLoginResponse {
  message?: string;
  data?: {
    user?: {
      id: number;
      name: string;
      email: string;
      email_verified_at: string | null;
      role: {
        id: number;
        name: string;
        slug: string;
      } | null;
    };
    token?: string;
    token_type?: string;
  };
  token?: string;
  token_type?: string;
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();

    const response = await fetch(`${BACKEND_URL}/api/login`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        email: body.email,
        password: body.password,
      }),
      cache: "no-store",
    });

    const payload =
      (await response.json()) as LaravelLoginResponse;

    if (!response.ok) {
      return NextResponse.json(
        {
          message: payload.message || "Email atau password tidak valid.",
        },
        { status: response.status }
      );
    }

    // Mendukung token pada root maupun di dalam data.
    const token = payload.token || payload.data?.token;
    const user = payload.data?.user;

    if (!token || !user) {
      console.error("Invalid Laravel login response structure:", {
        hasToken: Boolean(token),
        hasUser: Boolean(user),
        responseKeys: Object.keys(payload),
        dataKeys: payload.data
          ? Object.keys(payload.data)
          : [],
      });

      return NextResponse.json(
        {
          message: "Respons autentikasi dari server tidak valid.",
        },
        { status: 502 }
      );
    }

    const result = NextResponse.json({
      message: payload.message || "Login berhasil.",
      data: {
        user,
      },
    });

    result.cookies.set("pms_token", token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      path: "/",
      maxAge: 60 * 60 * 8,
    });

    return result;
  } catch (error) {
    console.error("Login API error:", error);

    return NextResponse.json(
      {
        message: "Tidak dapat menghubungi server autentikasi.",
      },
      { status: 502 }
    );
  }
}
