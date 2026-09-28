import { NextResponse, type NextRequest } from "next/server";

/**
 * Route protection: signed-out visitors to the staff console or applicant
 * portal are sent through the OAuth2 login flow. (Authorization itself is
 * enforced by the Laravel API on every call.)
 */
export function proxy(request: NextRequest) {
  const { pathname, search } = request.nextUrl;
  const portal = pathname.startsWith("/staff") ? "staff" : "applicant";
  const cookie = `nis_${portal}_session`;

  if (!request.cookies.has(cookie)) {
    const login = new URL(`/api/auth/login/${portal}`, request.url);
    login.searchParams.set("returnTo", pathname + search);
    return NextResponse.redirect(login);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/staff/:path*", "/portal/:path*"],
};
