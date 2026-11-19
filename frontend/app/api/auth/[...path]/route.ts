import { NextRequest, NextResponse } from "next/server";

const BACKEND_URL = (
  process.env.BACKEND_URL || "http://127.0.0.1:8000"
).replace(/\/+$/, "");

interface RouteContext {
  params: Promise<{
    path: string[];
  }>;
}

async function proxyRequest(
  request: NextRequest,
  context: RouteContext
) {
  const token = request.cookies.get("pms_token")?.value;

  if (!token) {
    return NextResponse.json(
      {
        message: "Unauthenticated.",
      },
      { status: 401 }
    );
  }

  try {
    const { path } = await context.params;

    const endpoint = path.map(encodeURIComponent).join("/");
    const queryString = request.nextUrl.search;

    const headers = new Headers({
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    });

    const contentType = request.headers.get("content-type");

    if (contentType) {
      headers.set("Content-Type", contentType);
    }

    const method = request.method.toUpperCase();

    const body =
      method === "GET" || method === "HEAD"
        ? undefined
        : await request.arrayBuffer();

    const backendResponse = await fetch(
      `${BACKEND_URL}/api/${endpoint}${queryString}`,
      {
        method,
        headers,
        body,
        cache: "no-store",
      }
    );

    const responseHeaders = new Headers();

    const backendContentType =
      backendResponse.headers.get("content-type");

    if (backendContentType) {
      responseHeaders.set("Content-Type", backendContentType);
    }

    responseHeaders.set("Cache-Control", "no-store");

    const response = new NextResponse(backendResponse.body, {
      status: backendResponse.status,
      headers: responseHeaders,
    });

    if (backendResponse.status === 401) {
      response.cookies.delete("pms_token");
    }

    return response;
  } catch {
    return NextResponse.json(
      {
        message: "Tidak dapat terhubung ke backend Laravel.",
      },
      { status: 502 }
    );
  }
}

export async function GET(
  request: NextRequest,
  context: RouteContext
) {
  return proxyRequest(request, context);
}

export async function POST(
  request: NextRequest,
  context: RouteContext
) {
  return proxyRequest(request, context);
}

export async function PUT(
  request: NextRequest,
  context: RouteContext
) {
  return proxyRequest(request, context);
}

export async function PATCH(
  request: NextRequest,
  context: RouteContext
) {
  return proxyRequest(request, context);
}

export async function DELETE(
  request: NextRequest,
  context: RouteContext
) {
  return proxyRequest(request, context);
}
