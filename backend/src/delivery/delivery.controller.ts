import { Controller, Get, Param, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { AdminGuard } from '../common/guards/admin.guard';
import { DeliveryService } from './delivery.service';

@ApiTags('delivery')
@ApiBearerAuth()
@UseGuards(AdminGuard)
@Controller('delivery')
export class DeliveryController {
  constructor(private readonly deliveryService: DeliveryService) {}

  @Get('partners')
  getPartners() {
    return this.deliveryService.getPartners();
  }

  @Post('assign')
  assignPartner() {
    return this.deliveryService.assignPartner();
  }

  @Post('status/:id')
  updateStatus(@Param('id') id: string) {
    return this.deliveryService.updateStatus(id);
  }
}
