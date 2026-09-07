import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';
import { toUploadPath } from '../common/upload-path';
import { PrismaService } from '../prisma/prisma.service';
import { CreateProductDto } from './dto/create-product.dto';
import { UpdateProductDto } from './dto/update-product.dto';

@Injectable()
export class ProductsService {
  constructor(private readonly prisma: PrismaService) {}

  async findAll() {
    const products = await this.prisma.product.findMany({
      where: { deletedAt: null },
      include: { category: true },
      orderBy: [{ sortOrder: 'asc' }, { createdAt: 'desc' }],
    });
    return successResponse(products.map((product) => this.formatProduct(product)), 'Products fetched successfully');
  }

  async findOne(id: string) {
    const product = await this.prisma.product.findFirst({
      where: { id, deletedAt: null },
      include: { category: true },
    });
    return successResponse(product ? this.formatProduct(product) : null, product ? 'Product fetched successfully' : 'Product not found');
  }

  async create(dto: CreateProductDto) {
    const category = await this.resolveCategory(dto.categoryId, dto.category);
    if (!category) {
      return successResponse(null, 'Category not found');
    }

    const product = await this.prisma.product.create({
      data: {
        name: dto.name,
        description: dto.description,
        categoryId: category.id,
        price: dto.price,
        discountedPrice: dto.discountedPrice,
        stock: dto.stock,
        images: (dto.images ?? []).map((image) => toUploadPath(image)),
        ...(dto.sortOrder !== undefined ? { sortOrder: dto.sortOrder } : {}),
      } as any,
    });
    return successResponse(this.formatProduct(product), 'Product created successfully');
  }

  async update(id: string, dto: UpdateProductDto) {
    const existing = await this.prisma.product.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Product not found');
    }

    const data: Record<string, unknown> = {};
    if (dto.name !== undefined) data.name = dto.name;
    if (dto.description !== undefined) data.description = dto.description;
    if (dto.price !== undefined) data.price = dto.price;
    if (dto.discountedPrice !== undefined) data.discountedPrice = dto.discountedPrice;
    if (dto.stock !== undefined) data.stock = dto.stock;
    if (dto.images !== undefined) data.images = dto.images.map((image) => toUploadPath(image));
    if (dto.sortOrder !== undefined) data.sortOrder = dto.sortOrder;

    if (dto.categoryId || dto.category) {
      const category = await this.resolveCategory(dto.categoryId, dto.category);
      if (category) {
        data.categoryId = category.id;
      }
    }

    const product = await this.prisma.product.update({ where: { id }, data: data as any });
    return successResponse(this.formatProduct(product), 'Product updated successfully');
  }

  private async resolveCategory(categoryId?: string, categorySlugOrName?: string) {
    if (categoryId) {
      return this.prisma.category.findFirst({ where: { id: categoryId, deletedAt: null } });
    }

    if (!categorySlugOrName) {
      return null;
    }

    return this.prisma.category.findFirst({ where: { slug: categorySlugOrName, deletedAt: null } });
  }

  async remove(id: string) {
    const existing = await this.prisma.product.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Product not found');
    }

    await this.prisma.product.update({ where: { id }, data: { deletedAt: new Date() } });
    return successResponse(null, 'Product deleted successfully');
  }

  private formatProduct<T extends { images: string[] }>(product: T): T {
    return { ...product, images: product.images.map((image) => toUploadPath(image)) };
  }
}
