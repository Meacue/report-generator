export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public data: unknown,
  ) {
    super(message);
    this.name = "ApiError";
  }

  static isApiError(error: unknown): error is ApiError {
    return (
      error !== null &&
      typeof error === "object" &&
      "name" in error &&
      (error as ApiError).name === "ApiError" &&
      "status" in error &&
      "data" in error
    );
  }
}

function buildUrl(path: string, params?: object): string {
  const base = `/api${path}`;

  if (!params) return base;

  const searchParams = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined) {
      searchParams.append(key, String(value));
    }
  }

  const qs = searchParams.toString();
  return qs ? `${base}?${qs}` : base;
}

export interface RequestOptions {
  params?: object;
  responseType?: "blob" | "json";
}

export interface ApiResponse<T> {
  data: T;
  status: number;
  headers: Record<string, string>;
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  options?: RequestOptions,
): Promise<ApiResponse<T>> {
  const url = buildUrl(path, options?.params);

  const init: RequestInit = {
    method,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
  };

  if (body !== undefined) {
    init.body = JSON.stringify(body);
  }

  const response = await fetch(url, init);

  if (!response.ok) {
    let errorData: unknown;
    try {
      errorData = await response.json();
    } catch {
      errorData = await response.text();
    }
    console.error("API Error:", errorData);
    throw new ApiError(
      `Request failed with status ${response.status}`,
      response.status,
      errorData,
    );
  }

  let data: T;

  if (options?.responseType === "blob") {
    data = (await response.blob()) as T;
  } else if (response.status === 204) {
    data = null as T;
  } else {
    const text = await response.text();
    data = text.length > 0 ? (JSON.parse(text) as T) : (null as T);
  }

  const headers: Record<string, string> = {};
  response.headers.forEach((value, key) => {
    headers[key] = value;
  });

  return { data, status: response.status, headers };
}

const apiClient = {
  get: <T>(url: string, options?: RequestOptions) =>
    request<T>("GET", url, undefined, options),

  post: <T>(url: string, body?: unknown, options?: RequestOptions) =>
    request<T>("POST", url, body, options),

  put: <T>(url: string, body?: unknown, options?: RequestOptions) =>
    request<T>("PUT", url, body, options),

  delete: <T>(url: string, options?: RequestOptions) =>
    request<T>("DELETE", url, undefined, options),
};

export default apiClient;
