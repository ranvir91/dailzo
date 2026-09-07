import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';
import { PrismaService } from '../prisma/prisma.service';

export interface AddressDto {
  label: string;
  line1: string;
  line2?: string | null;
  city: string;
  state?: string | null;
  pincode: string;
  isDefault?: boolean;
}

@Injectable()
export class AddressesService {
  constructor(private readonly prisma: PrismaService) {}

  async findAll(userId: string) {
    const addresses = await this.prisma.address.findMany({
      where: { userId, deletedAt: null },
      orderBy: { createdAt: 'desc' },
    });
    return successResponse(addresses, 'Addresses fetched successfully');
  }

  async create(userId: string, dto: AddressDto) {
    if (dto.isDefault) {
      await this.prisma.address.updateMany({ where: { userId, deletedAt: null }, data: { isDefault: false } });
    }

    const address = await this.prisma.address.create({
      data: {
        userId,
        label: dto.label,
        line1: dto.line1,
        line2: dto.line2 ?? null,
        city: dto.city,
        state: dto.state ?? null,
        pincode: dto.pincode,
        isDefault: dto.isDefault ?? false,
      },
    });
    return successResponse(address, 'Address created successfully');
  }

  async update(userId: string, id: string, dto: Partial<AddressDto>) {
    const existing = await this.prisma.address.findFirst({ where: { id, userId, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Address not found');
    }

    if (dto.isDefault) {
      await this.prisma.address.updateMany({ where: { userId, deletedAt: null, id: { not: id } }, data: { isDefault: false } });
    }

    const data: Record<string, unknown> = {};
    if (dto.label !== undefined) data.label = dto.label;
    if (dto.line1 !== undefined) data.line1 = dto.line1;
    if (dto.line2 !== undefined) data.line2 = dto.line2;
    if (dto.city !== undefined) data.city = dto.city;
    if (dto.state !== undefined) data.state = dto.state;
    if (dto.pincode !== undefined) data.pincode = dto.pincode;
    if (dto.isDefault !== undefined) data.isDefault = dto.isDefault;

    const address = await this.prisma.address.update({ where: { id }, data });
    return successResponse(address, 'Address updated successfully');
  }

  async remove(userId: string, id: string) {
    const existing = await this.prisma.address.findFirst({ where: { id, userId, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Address not found');
    }

    await this.prisma.address.update({ where: { id }, data: { deletedAt: new Date() } });
    return successResponse({ id }, 'Address removed successfully');
  }
}
