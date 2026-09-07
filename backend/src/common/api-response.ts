export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message: string;
  errors?: string[];
}

export function successResponse<T>(data: T, message = 'Request successful'): ApiResponse<T> {
  return {
    success: true,
    data,
    message,
  };
}

export function errorResponse(message: string, errors: string[] = []): ApiResponse<null> {
  return {
    success: false,
    message,
    errors,
  };
}
