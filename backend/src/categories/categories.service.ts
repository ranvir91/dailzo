import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';
import { toUploadPath } from '../common/upload-path';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class CategoriesService {
  constructor(private readonly prisma: PrismaService) {}

  async findAll() {
    const categories = await this.prisma.category.findMany({
      where: { deletedAt: null },
      orderBy: [{ sortOrder: 'asc' }, { createdAt: 'desc' }],
    });
    return successResponse(categories.map((category) => this.formatCategory(category)), 'Categories fetched successfully');
  }

  async create(name: string, iconUrl: string | null = null, description: string | null = null, sortOrder?: number | string) {
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    const data: Record<string, unknown> = { name, slug, iconUrl: toUploadPath(iconUrl), description };
    if (sortOrder !== undefined && sortOrder !== '') {
      data.sortOrder = Number(sortOrder);
    }
    const category = await this.prisma.category.create({ data: data as any });
    return successResponse(this.formatCategory(category), 'Category created successfully');
  }

  async update(id: string, dto: { name?: string; iconUrl?: string | null; description?: string | null; isActive?: boolean; sortOrder?: number | string }) {
    const existing = await this.prisma.category.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Category not found');
    }

    const data: Record<string, unknown> = {};
    if (dto.name !== undefined) {
      data.name = dto.name;
      data.slug = dto.name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    }
    if (dto.iconUrl !== undefined) {
      data.iconUrl = toUploadPath(dto.iconUrl);
    }
    if (dto.description !== undefined) {
      data.description = dto.description;
    }
    if (dto.isActive !== undefined) {
      data.isActive = dto.isActive;
    }
    if (dto.sortOrder !== undefined) {
      data.sortOrder = dto.sortOrder === '' ? 0 : Number(dto.sortOrder);
    }

    const category = await this.prisma.category.update({ where: { id }, data: data as any });
    return successResponse(this.formatCategory(category), 'Category updated successfully');
  }

  async updateStatus(id: string, isActive: boolean) {
    const existing = await this.prisma.category.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Category not found');
    }

    const category = await this.prisma.category.update({ where: { id }, data: { isActive } as any });
    return successResponse(this.formatCategory(category), isActive ? 'Category activated successfully' : 'Category deactivated successfully');
  }

  async remove(id: string) {
    const existing = await this.prisma.category.findFirst({ where: { id, deletedAt: null } });
    if (!existing) {
      return successResponse(null, 'Category not found');
    }

    await this.prisma.category.update({ where: { id }, data: { deletedAt: new Date() } });
    return successResponse(null, 'Category deleted successfully');
  }

  private formatCategory<T extends { iconUrl: string | null }>(category: T): T {
    return { ...category, iconUrl: toUploadPath(category.iconUrl) };
  }
}
