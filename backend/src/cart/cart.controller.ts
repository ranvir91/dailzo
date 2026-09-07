import { Body, Controller, Delete, Get, Param, Patch, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import { AuthGuard } from '../common/guards/auth.guard';
import { User } from '../generated/prisma-client';
import { CartService } from './cart.service';

class CartItemDto {
  productId: string;
  quantity: number;
}

@ApiTags('cart')
@ApiBearerAuth()
@UseGuards(AuthGuard)
@Controller('cart')
export class CartController {
  constructor(private readonly cartService: CartService) {}

  @Get()
  getCart(@CurrentUser() user: User) {
    return this.cartService.getCart(user.id);
  }

  @Post('items')
  addItem(@Body() dto: CartItemDto, @CurrentUser() user: User) {
    return this.cartService.addItem(user.id, dto);
  }

  @Patch('items/:id')
  updateItem(@Param('id') id: string, @Body() dto: { quantity: number }, @CurrentUser() user: User) {
    return this.cartService.updateItem(user.id, id, dto.quantity);
  }

  @Delete('items/:id')
  removeItem(@Param('id') id: string, @CurrentUser() user: User) {
    return this.cartService.removeItem(user.id, id);
  }
}
