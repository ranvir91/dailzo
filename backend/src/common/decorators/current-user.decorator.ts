import { createParamDecorator, ExecutionContext } from '@nestjs/common';
import { User } from '../../generated/prisma-client';

// Reads the user attached by AuthGuard/AdminGuard. Only usable on routes guarded by one of them.
export const CurrentUser = createParamDecorator((_data: unknown, ctx: ExecutionContext): User => {
  const request = ctx.switchToHttp().getRequest();
  return request.user;
});
