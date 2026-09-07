import { Body, Controller, Get, Param, Patch, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiBody, ApiOperation, ApiParam, ApiTags } from '@nestjs/swagger';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import { AdminGuard } from '../common/guards/admin.guard';
import { AuthGuard } from '../common/guards/auth.guard';
import { User } from '../generated/prisma-client';
import { OrdersService } from './orders.service';

@ApiTags('orders')
@ApiBearerAuth()
@UseGuards(AuthGuard)
@Controller('orders')
export class OrdersController {
  constructor(private readonly ordersService: OrdersService) {}

  @ApiOperation({ summary: 'Create an order' })
  @ApiBody({ schema: { example: { addressId: 'addr-1', paymentMethod: 'COD', total: 140, items: [{ productId: 'product-1', quantity: 2 }] } } })
  @Post()
  create(@Body() dto: any, @CurrentUser() user: User) {
    return this.ordersService.create(user.id, dto);
  }

  @ApiOperation({ summary: "List the caller's orders (all orders for admins)" })
  @Get()
  findAll(@CurrentUser() user: User) {
    return this.ordersService.findAll(user.id, user.role === 'ADMIN');
  }

  @ApiOperation({ summary: 'Get an order by id' })
  @ApiParam({ name: 'id', example: 'order-1' })
  @Get(':id')
  findOne(@Param('id') id: string, @CurrentUser() user: User) {
    return this.ordersService.findOne(id, user.id, user.role === 'ADMIN');
  }

  @ApiOperation({ summary: 'Cancel an order' })
  @ApiParam({ name: 'id', example: 'order-1' })
  @Post(':id/cancel')
  cancel(@Param('id') id: string, @CurrentUser() user: User) {
    return this.ordersService.cancel(id, user.id, user.role === 'ADMIN');
  }

  @ApiOperation({ summary: 'Update order status' })
  @ApiParam({ name: 'id', example: 'order-1' })
  @UseGuards(AdminGuard)
  @Patch(':id/status')
  updateStatus(@Param('id') id: string, @Body() body: { status: string }) {
    return this.ordersService.updateStatus(id, body.status);
  }
}
