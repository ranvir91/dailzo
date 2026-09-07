import { BadRequestException, Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';
import { PrismaService } from '../prisma/prisma.service';

interface CreateOrderItemDto {
  productId: string;
  quantity: number;
}

interface CreateOrderDto {
  addressId?: string;
  paymentMethod: string;
  total: number;
  items: CreateOrderItemDto[];
}

const STATUS_TRANSITIONS = new Map<string, string[]>([
  ['PENDING', ['PAYMENT_PENDING', 'CANCELLED']],
  ['PAYMENT_PENDING', ['CONFIRMED', 'CANCELLED']],
  ['CONFIRMED', ['PROCESSING', 'CANCELLED']],
  ['PROCESSING', ['PACKED', 'CANCELLED']],
  ['PACKED', ['ASSIGNED', 'CANCELLED']],
  ['ASSIGNED', ['PICKED_UP', 'CANCELLED']],
  ['PICKED_UP', ['OUT_FOR_DELIVERY', 'CANCELLED']],
  ['OUT_FOR_DELIVERY', ['DELIVERED', 'CANCELLED']],
]);

@Injectable()
export class OrdersService {
  constructor(private readonly prisma: PrismaService) {}

  async create(userId: string, dto: CreateOrderDto) {
    if (!dto.items?.length) {
      throw new BadRequestException('Order must contain at least one item.');
    }

    const storeSettings = await this.prisma.storeSetting.findFirst();
    if (storeSettings?.minOrderValueEnabled && dto.total < Number(storeSettings.minOrderValue)) {
      throw new BadRequestException(`Minimum order value is ₹${storeSettings.minOrderValue}. Please add more items to your cart.`);
    }

    const products = await this.prisma.product.findMany({
      where: { id: { in: dto.items.map((item) => item.productId) }, deletedAt: null },
    });
    const productById = new Map(products.map((product) => [product.id, product]));

    const order = await this.prisma.order.create({
      data: {
        userId,
        status: 'PENDING',
        paymentMethod: dto.paymentMethod,
        total: dto.total,
        addressId: dto.addressId,
        items: {
          create: dto.items.map((item) => {
            const product = productById.get(item.productId);
            const price = product ? Number(product.discountedPrice ?? product.price) : 0;
            return { productId: item.productId, quantity: item.quantity, price };
          }),
        },
      },
      include: { items: { include: { product: true } } },
    });

    return successResponse(order, 'Order created successfully');
  }

  async findAll(userId: string, isAdmin: boolean) {
    const orders = await this.prisma.order.findMany({
      where: isAdmin ? {} : { userId },
      include: { items: { include: { product: true } } },
      orderBy: { createdAt: 'desc' },
    });
    return successResponse(orders, 'Orders fetched successfully');
  }

  async findOne(id: string, userId: string, isAdmin: boolean) {
    const order = await this.prisma.order.findFirst({
      where: { id, ...(isAdmin ? {} : { userId }) },
      include: { items: { include: { product: true } } },
    });
    return successResponse(order, order ? 'Order fetched successfully' : 'Order not found');
  }

  async cancel(id: string, userId: string, isAdmin: boolean) {
    const order = await this.prisma.order.findFirst({
      where: { id, ...(isAdmin ? {} : { userId }) },
      include: { items: { include: { product: true } } },
    });
    if (!order) {
      return successResponse(null, 'Order not found');
    }
    if (order.status === 'DELIVERED' || order.status === 'CANCELLED') {
      return successResponse(order, 'Order cannot be cancelled in its current state');
    }

    const updated = await this.prisma.order.update({
      where: { id },
      data: { status: 'CANCELLED' },
      include: { items: { include: { product: true } } },
    });
    return successResponse(updated, 'Order cancelled successfully');
  }

  async updateStatus(id: string, status: string) {
    const order = await this.prisma.order.findFirst({ where: { id }, include: { items: true } });
    if (!order) {
      return successResponse(null, 'Order not found');
    }

    const allowed = STATUS_TRANSITIONS.get(order.status) ?? [];
    if (!allowed.includes(status)) {
      return successResponse(order, 'Invalid order status transition');
    }

    const updated = await this.prisma.order.update({
      where: { id },
      data: { status },
      include: { items: { include: { product: true } } },
    });
    return successResponse(updated, 'Order status updated successfully');
  }
}
