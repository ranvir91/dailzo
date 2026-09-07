import { Body, Controller, Delete, Get, Param, Post, Put, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import { AuthGuard } from '../common/guards/auth.guard';
import { User } from '../generated/prisma-client';
import { AddressDto, AddressesService } from './addresses.service';

@ApiTags('addresses')
@ApiBearerAuth()
@UseGuards(AuthGuard)
@Controller('addresses')
export class AddressesController {
  constructor(private readonly addressesService: AddressesService) {}

  @Get()
  findAll(@CurrentUser() user: User) {
    return this.addressesService.findAll(user.id);
  }

  @Post()
  create(@Body() dto: AddressDto, @CurrentUser() user: User) {
    return this.addressesService.create(user.id, dto);
  }

  @Put(':id')
  update(@Param('id') id: string, @Body() dto: Partial<AddressDto>, @CurrentUser() user: User) {
    return this.addressesService.update(user.id, id, dto);
  }

  @Delete(':id')
  remove(@Param('id') id: string, @CurrentUser() user: User) {
    return this.addressesService.remove(user.id, id);
  }
}
