import { Body, Controller, Delete, Get, Param, Patch, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { AdminGuard } from '../common/guards/admin.guard';
import { CouponInput, CouponsService } from './coupons.service';

@ApiTags('coupons')
@Controller('coupons')
export class CouponsController {
  constructor(private readonly couponsService: CouponsService) {}

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Get('admin')
  findAllAdmin() {
    return this.couponsService.findAllAdmin();
  }

  @Get()
  findActive() {
    return this.couponsService.findActive();
  }

  @Get(':code')
  findOne(@Param('code') code: string) {
    return this.couponsService.findOne(code);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Post()
  create(@Body() body: CouponInput) {
    return this.couponsService.create(body);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Patch(':id')
  update(@Param('id') id: string, @Body() body: CouponInput) {
    return this.couponsService.update(id, body);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Patch(':id/status')
  updateStatus(@Param('id') id: string, @Body() body: { isActive: boolean }) {
    return this.couponsService.updateStatus(id, body.isActive);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Delete(':id')
  remove(@Param('id') id: string) {
    return this.couponsService.remove(id);
  }
}
