import { BadRequestException, Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';
import { PrismaService } from '../prisma/prisma.service';

const USER_ROLES = ['CUSTOMER', 'ADMIN'];

export interface UpdateUserInput {
  name?: string | null;
  email?: string | null;
  phone?: string;
  role?: string;
}

@Injectable()
export class UsersService {
  constructor(private readonly prisma: PrismaService) {}

  async findAll() {
    const users = await this.prisma.user.findMany({ where: { deletedAt: null } });
    return successResponse(users, 'Users fetched successfully');
  }

  async findOne(id: string) {
    const user = await this.prisma.user.findFirst({ where: { id, deletedAt: null } });
    return successResponse(user ?? null, user ? 'User fetched successfully' : 'User not found');
  }

  async update(id: string, dto: UpdateUserInput, allowRoleChange: boolean) {
    const existing = await this.prisma.user.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'User not found');
    }

    if (dto.role !== undefined && !USER_ROLES.includes(dto.role)) {
      throw new BadRequestException('Role must be CUSTOMER or ADMIN');
    }
    if (dto.phone !== undefined && !dto.phone.trim()) {
      throw new BadRequestException('Phone number cannot be empty');
    }

    const data: Record<string, unknown> = {};
    if (dto.name !== undefined) data.name = dto.name;
    if (dto.email !== undefined) data.email = dto.email || null;
    if (dto.phone !== undefined) data.phone = dto.phone.trim();
    if (allowRoleChange && dto.role !== undefined) data.role = dto.role;

    const user = await this.prisma.user.update({ where: { id }, data });
    return successResponse(user, 'User updated successfully');
  }

  async remove(id: string) {
    const existing = await this.prisma.user.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'User not found');
    }

    await this.prisma.user.update({ where: { id }, data: { deletedAt: new Date() } });
    return successResponse(null, 'User deleted successfully');
  }
}
