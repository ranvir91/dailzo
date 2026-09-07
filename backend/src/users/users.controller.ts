import { Body, Controller, Delete, Get, Param, Put, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { successResponse } from '../common/api-response';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import { AdminGuard } from '../common/guards/admin.guard';
import { AuthGuard } from '../common/guards/auth.guard';
import { User } from '../generated/prisma-client';
import { UpdateUserInput, UsersService } from './users.service';

@ApiTags('users')
@Controller('users')
export class UsersController {
  constructor(private readonly usersService: UsersService) {}

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Get()
  findAll() {
    return this.usersService.findAll();
  }

  @ApiBearerAuth()
  @UseGuards(AuthGuard)
  @Get(':id')
  findOne(@Param('id') id: string, @CurrentUser() user: User) {
    if (user.id !== id && user.role !== 'ADMIN') {
      return successResponse(null, 'User not found');
    }
    return this.usersService.findOne(id);
  }

  @ApiBearerAuth()
  @UseGuards(AuthGuard)
  @Put(':id')
  update(@Param('id') id: string, @Body() body: UpdateUserInput, @CurrentUser() user: User) {
    if (user.id !== id && user.role !== 'ADMIN') {
      return successResponse(null, 'User not found');
    }
    // Only admins may change a role; a self-update from a non-admin silently keeps their current role.
    return this.usersService.update(id, body, user.role === 'ADMIN');
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Delete(':id')
  remove(@Param('id') id: string, @CurrentUser() user: User) {
    if (user.id === id) {
      return successResponse(null, 'You cannot delete your own account');
    }
    return this.usersService.remove(id);
  }
}
