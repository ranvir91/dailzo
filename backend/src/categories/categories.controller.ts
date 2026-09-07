import { Body, Controller, Delete, Get, Param, Patch, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { AdminGuard } from '../common/guards/admin.guard';
import { CategoriesService } from './categories.service';

@ApiTags('categories')
@Controller('categories')
export class CategoriesController {
  constructor(private readonly categoriesService: CategoriesService) {}

  @Get()
  findAll() {
    return this.categoriesService.findAll();
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Post()
  create(@Body() body: { name: string; iconUrl?: string | null; description?: string | null; sortOrder?: number | string }) {
    return this.categoriesService.create(body.name, body.iconUrl ?? null, body.description ?? null, body.sortOrder);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Patch(':id')
  update(@Param('id') id: string, @Body() body: { name?: string; iconUrl?: string | null; description?: string | null; isActive?: boolean; sortOrder?: number | string }) {
    return this.categoriesService.update(id, body);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Patch(':id/status')
  updateStatus(@Param('id') id: string, @Body() body: { isActive: boolean }) {
    return this.categoriesService.updateStatus(id, body.isActive);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Delete(':id')
  remove(@Param('id') id: string) {
    return this.categoriesService.remove(id);
  }
}
