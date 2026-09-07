import { Controller, Get } from '@nestjs/common';
import { ApiTags } from '@nestjs/swagger';
import { SettingsService } from './settings.service';

@ApiTags('serviceable-pincodes')
@Controller('serviceable-pincodes')
export class ServiceablePincodesController {
  constructor(private readonly settingsService: SettingsService) {}

  @Get()
  list() {
    return this.settingsService.listActiveServicePincodes();
  }
}