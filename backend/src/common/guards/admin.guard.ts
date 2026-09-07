import { ExecutionContext, ForbiddenException, Injectable } from '@nestjs/common';
import { AuthGuard } from './auth.guard';

// Same as AuthGuard, but additionally requires the resolved user to have the ADMIN role.
@Injectable()
export class AdminGuard extends AuthGuard {
  async canActivate(context: ExecutionContext): Promise<boolean> {
    await super.canActivate(context);
    const request = context.switchToHttp().getRequest();
    if (request.user.role !== 'ADMIN') {
      throw new ForbiddenException('Admin access required');
    }
    return true;
  }
}
