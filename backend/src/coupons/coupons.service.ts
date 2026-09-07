import { BadRequestException, Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';
import { PrismaService } from '../prisma/prisma.service';

const COUPON_TYPES = ['FIXED', 'PERCENTAGE'];

// This store operates in India, so a plain "YYYY-MM-DD" from the admin's date picker means
// midnight IST (UTC+5:30), not midnight UTC — otherwise a coupon set to "start today" only goes
// live 5.5 hours late (and one set to "end today" would expire at the start of that day instead
// of the end of it). Full ISO datetime strings (already carrying an explicit instant) pass through
// untouched. Hardcoded rather than read from the server's TZ env var, since that varies by
// deployment environment and isn't necessarily IST.
const IST_OFFSET_MS = (5 * 60 + 30) * 60 * 1000;
const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

function parseCouponDate(value: string, endOfDay: boolean): Date {
  if (!DATE_ONLY_PATTERN.test(value)) {
    return new Date(value);
  }
  const time = endOfDay ? 'T23:59:59.999' : 'T00:00:00.000';
  return new Date(new Date(`${value}${time}Z`).getTime() - IST_OFFSET_MS);
}

export interface CouponInput {
  code?: string;
  description?: string | null;
  type?: string;
  discount?: number | string;
  maxDiscountAmount?: number | string | null;
  minOrderValue?: number | string;
  startsAt?: string | null;
  expiresAt?: string | null;
  usageLimit?: number | string | null;
  perUserLimit?: number | string | null;
  firstOrderOnly?: boolean;
  isActive?: boolean;
}

@Injectable()
export class CouponsService {
  constructor(private readonly prisma: PrismaService) {}

  async findActive() {
    const now = new Date();
    const coupons = await this.prisma.coupon.findMany({
      where: {
        deletedAt: null,
        isActive: true,
        AND: [
          { OR: [{ startsAt: null }, { startsAt: { lte: now } }] },
          { OR: [{ expiresAt: null }, { expiresAt: { gte: now } }] },
        ],
      },
      orderBy: { createdAt: 'desc' },
    });
    return successResponse(coupons, 'Coupons fetched successfully');
  }

  async findAllAdmin() {
    const coupons = await this.prisma.coupon.findMany({ where: { deletedAt: null }, orderBy: { createdAt: 'desc' } });
    return successResponse(coupons, 'Coupons fetched successfully');
  }

  async findOne(code: string) {
    const coupon = await this.prisma.coupon.findFirst({ where: { code: code.toUpperCase(), deletedAt: null } });
    return successResponse(coupon, coupon ? 'Coupon found' : 'Coupon not found');
  }

  async create(dto: CouponInput) {
    this.validate(dto, true);
    const coupon = await this.prisma.coupon.create({ data: this.toData(dto, true) as any });
    return successResponse(coupon, 'Coupon created successfully');
  }

  async update(id: string, dto: CouponInput) {
    const existing = await this.prisma.coupon.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Coupon not found');
    }

    this.validate(dto, false);
    const coupon = await this.prisma.coupon.update({ where: { id }, data: this.toData(dto, false) as any });
    return successResponse(coupon, 'Coupon updated successfully');
  }

  async updateStatus(id: string, isActive: boolean) {
    const existing = await this.prisma.coupon.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Coupon not found');
    }

    const coupon = await this.prisma.coupon.update({ where: { id }, data: { isActive } });
    return successResponse(coupon, isActive ? 'Coupon activated successfully' : 'Coupon deactivated successfully');
  }

  async remove(id: string) {
    const existing = await this.prisma.coupon.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Coupon not found');
    }

    await this.prisma.coupon.update({ where: { id }, data: { deletedAt: new Date() } });
    return successResponse(null, 'Coupon deleted successfully');
  }

  private validate(dto: CouponInput, isCreate: boolean) {
    if (isCreate && (!dto.code || !dto.code.trim())) {
      throw new BadRequestException('Coupon code is required');
    }
    if ((isCreate || dto.type !== undefined) && !COUPON_TYPES.includes(String(dto.type))) {
      throw new BadRequestException('Coupon type must be FIXED or PERCENTAGE');
    }
    if (isCreate && (dto.discount === undefined || dto.discount === '' || Number.isNaN(Number(dto.discount)) || Number(dto.discount) <= 0)) {
      throw new BadRequestException('Enter a valid discount value');
    }
    if (dto.discount !== undefined && dto.type === 'PERCENTAGE' && Number(dto.discount) > 100) {
      throw new BadRequestException('Percentage discount cannot exceed 100');
    }
    if (dto.startsAt && dto.expiresAt && parseCouponDate(dto.startsAt, false) > parseCouponDate(dto.expiresAt, true)) {
      throw new BadRequestException('Validity start date must be before the end date');
    }
  }

  private toData(dto: CouponInput, isCreate: boolean) {
    const data: Record<string, unknown> = {};
    if (dto.code !== undefined) data.code = dto.code!.trim().toUpperCase();
    if (dto.description !== undefined) data.description = dto.description || null;
    if (dto.type !== undefined) data.type = dto.type;
    if (dto.discount !== undefined) data.discount = Number(dto.discount);
    if (dto.maxDiscountAmount !== undefined) {
      data.maxDiscountAmount = dto.maxDiscountAmount === '' || dto.maxDiscountAmount === null ? null : Number(dto.maxDiscountAmount);
    }
    if (dto.minOrderValue !== undefined) {
      data.minOrderValue = dto.minOrderValue === '' || dto.minOrderValue === null ? 0 : Number(dto.minOrderValue);
    }
    if (dto.startsAt !== undefined) data.startsAt = dto.startsAt ? parseCouponDate(dto.startsAt, false) : null;
    if (dto.expiresAt !== undefined) data.expiresAt = dto.expiresAt ? parseCouponDate(dto.expiresAt, true) : null;
    if (dto.usageLimit !== undefined) {
      data.usageLimit = dto.usageLimit === '' || dto.usageLimit === null ? null : Number(dto.usageLimit);
    }
    if (dto.perUserLimit !== undefined) {
      data.perUserLimit = dto.perUserLimit === '' || dto.perUserLimit === null ? null : Number(dto.perUserLimit);
    }
    if (dto.firstOrderOnly !== undefined) data.firstOrderOnly = Boolean(dto.firstOrderOnly);
    if (dto.isActive !== undefined) data.isActive = Boolean(dto.isActive);

    if (isCreate) {
      if (data.isActive === undefined) data.isActive = true;
      if (data.perUserLimit === undefined) data.perUserLimit = 1;
      if (data.minOrderValue === undefined) data.minOrderValue = 0;
    }

    return data;
  }
}
