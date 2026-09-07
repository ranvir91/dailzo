import { Body, Controller, Delete, Get, Param, Patch, Post, Put, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { AdminGuard } from '../common/guards/admin.guard';
import { SettingsService } from './settings.service';

@ApiTags('settings')
@Controller('settings')
export class SettingsController {
  constructor(private readonly settingsService: SettingsService) {}

  @Get('store')
  getStoreSettings() {
    return this.settingsService.getStoreSettings();
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Put('store')
  updateStoreSettings(
    @Body()
    body: {
      deliveryCharges: number | string;
      minOrderValue?: number | string;
      minOrderValueEnabled?: boolean;
      maintenanceMode?: boolean;
      appVersion: string;
      storeOpenTime: string;
      storeCloseTime: string;
      paymentMethods: string[];
    },
  ) {
    return this.settingsService.updateStoreSettings(body);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Patch('store/maintenance-mode')
  setMaintenanceMode(@Body() body: { maintenanceMode: boolean }) {
    return this.settingsService.setMaintenanceMode(body.maintenanceMode);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Patch('store/min-order-value')
  setMinOrderValueEnabled(@Body() body: { enabled: boolean; minOrderValue?: number | string }) {
    return this.settingsService.setMinOrderValueEnabled(body.enabled, body.minOrderValue);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Get('pincodes')
  listServicePincodes() {
    return this.settingsService.listServicePincodes();
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Post('pincodes')
  createServicePincode(@Body() body: { pincode: string; isActive?: boolean }) {
    return this.settingsService.createServicePincode(body.pincode, body.isActive);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Patch('pincodes/:id')
  updateServicePincode(@Param('id') id: string, @Body() body: { pincode?: string; isActive?: boolean }) {
    return this.settingsService.updateServicePincode(id, body);
  }

  @ApiBearerAuth()
  @UseGuards(AdminGuard)
  @Delete('pincodes/:id')
  removeServicePincode(@Param('id') id: string) {
    return this.settingsService.removeServicePincode(id);
  }

  @Get('pincodes/lookup/:pincode')
  checkServicePincode(@Param('pincode') pincode: string) {
    return this.settingsService.checkServicePincode(pincode);
  }
}
