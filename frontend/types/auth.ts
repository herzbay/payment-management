export interface AuthRole {
  id: number;
  name: string;
  slug: string;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  role: AuthRole | null;
}

export interface LoginResponse {
  message: string;
  data: {
    user: AuthUser;
  };
  token?: string;
  token_type?: string;
}

export interface UserResponse {
  message: string;
  data: AuthUser;
}
