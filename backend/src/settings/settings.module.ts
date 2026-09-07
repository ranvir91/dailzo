import { Module } from '@nestjs/common';
import { PrismaModule } from '../prisma/prisma.module';
import { ServiceablePincodesController } from './serviceable-pincodes.controller';
import { SettingsController } from './settings.controller';
import { SettingsService } from './settings.service';

@Module({
  imports: [PrismaModule],
  controllers: [SettingsController, ServiceablePincodesController],
  providers: [SettingsService],
})
export class SettingsModule {}
