import { CanActivate, ExecutionContext, Injectable, UnauthorizedException } from '@nestjs/common';
import { PrismaService } from '../../prisma/prisma.service';
import { getUserIdFromAuthHeader } from '../auth-token';

// Verifies the caller sent a valid access token and attaches the resolved user to the request
// as `request.user`. Use `@CurrentUser()` in controllers to read it.
@Injectable()
export class AuthGuard implements CanActivate {
  constructor(protected readonly prisma: PrismaService) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();
    const userId = getUserIdFromAuthHeader(request.headers?.authorization);
    if (!userId) {
      throw new UnauthorizedException('Please login to continue');
    }

    const user = await this.prisma.user.findFirst({ where: { id: userId, deletedAt: null } });
    if (!user) {
      throw new UnauthorizedException('Invalid or expired session');
    }

    request.user = user;
    return true;
  }
}
