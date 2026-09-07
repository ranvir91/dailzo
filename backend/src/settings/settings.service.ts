import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';
import { PrismaService } from '../prisma/prisma.service';

const defaultSettings = {
  deliveryCharges: 29,
  tax: 5,
  minOrderValue: 0,
  minOrderValueEnabled: false,
  maintenanceMode: false,
  appVersion: '1.0.0',
  storeOpenTime: '09:00',
  storeCloseTime: '21:00',
  paymentMethods: ['COD', 'UPI', 'Cards'],
};

@Injectable()
export class SettingsService {
  constructor(private readonly prisma: PrismaService) {}

  async getStoreSettings() {
    const settings = await this.ensureStoreSettings();
    return successResponse(settings, 'Store settings fetched successfully');
  }

  async updateStoreSettings(dto: {
    deliveryCharges: number | string;
    minOrderValue?: number | string;
    minOrderValueEnabled?: boolean;
    maintenanceMode?: boolean;
    appVersion: string;
    storeOpenTime: string;
    storeCloseTime: string;
    paymentMethods: string[];
  }) {
    const existing = await this.ensureStoreSettings();
    const settings = await this.prisma.storeSetting.update({
      where: { id: existing.id },
      data: {
        deliveryCharges: Number(dto.deliveryCharges),
        minOrderValue: dto.minOrderValue !== undefined ? Number(dto.minOrderValue) : existing.minOrderValue,
        minOrderValueEnabled: dto.minOrderValueEnabled !== undefined ? dto.minOrderValueEnabled : existing.minOrderValueEnabled,
        maintenanceMode: dto.maintenanceMode !== undefined ? dto.maintenanceMode : existing.maintenanceMode,
        appVersion: dto.appVersion,
        storeOpenTime: dto.storeOpenTime,
        storeCloseTime: dto.storeCloseTime,
        paymentMethods: dto.paymentMethods,
      },
    });
    return successResponse(settings, 'Store settings updated successfully');
  }

  async setMaintenanceMode(maintenanceMode: boolean) {
    const existing = await this.ensureStoreSettings();
    const settings = await this.prisma.storeSetting.update({ where: { id: existing.id }, data: { maintenanceMode } });
    return successResponse(settings, maintenanceMode ? 'Maintenance mode enabled' : 'Maintenance mode disabled');
  }

  async setMinOrderValueEnabled(enabled: boolean, minOrderValue?: number | string) {
    const existing = await this.ensureStoreSettings();
    const settings = await this.prisma.storeSetting.update({
      where: { id: existing.id },
      data: {
        minOrderValueEnabled: enabled,
        minOrderValue: minOrderValue !== undefined ? Number(minOrderValue) : existing.minOrderValue,
      },
    });
    return successResponse(settings, enabled ? 'Minimum order value enforcement enabled' : 'Minimum order value enforcement disabled');
  }

  private async ensureStoreSettings() {
    const existing = await this.prisma.storeSetting.findFirst();
    if (existing) {
      return existing;
    }

    return this.prisma.storeSetting.create({
      data: {
        deliveryCharges: defaultSettings.deliveryCharges,
        tax: defaultSettings.tax,
        minOrderValue: defaultSettings.minOrderValue,
        minOrderValueEnabled: defaultSettings.minOrderValueEnabled,
        maintenanceMode: defaultSettings.maintenanceMode,
        appVersion: defaultSettings.appVersion,
        storeOpenTime: defaultSettings.storeOpenTime,
        storeCloseTime: defaultSettings.storeCloseTime,
        paymentMethods: defaultSettings.paymentMethods,
      },
    });
  }

  async listServicePincodes() {
    const pincodes = await this.prisma.servicePincode.findMany({ orderBy: { createdAt: 'desc' } });
    return successResponse(pincodes, 'Service pincodes fetched successfully');
  }

  async listActiveServicePincodes() {
    const pincodes = await this.prisma.servicePincode.findMany({
      where: { isActive: true },
      orderBy: { pincode: 'asc' },
      select: { pincode: true },
    });
    return successResponse(pincodes.map(({ pincode }) => pincode), 'Serviceable pincodes fetched successfully');
  }

  async createServicePincode(pincode: string, isActive = true) {
    const trimmed = pincode.trim();
    const created = await this.prisma.servicePincode.create({ data: { pincode: trimmed, isActive } });
    return successResponse(created, 'Service pincode added successfully');
  }

  async updateServicePincode(id: string, dto: { pincode?: string; isActive?: boolean }) {
    const existing = await this.prisma.servicePincode.findFirst({ where: { id } });
    if (!existing) {
      return successResponse(null, 'Pincode not found');
    }

    const data: Record<string, unknown> = {};
    if (dto.pincode !== undefined) data.pincode = dto.pincode.trim();
    if (dto.isActive !== undefined) data.isActive = dto.isActive;

    const updated = await this.prisma.servicePincode.update({ where: { id }, data });
    return successResponse(updated, 'Service pincode updated successfully');
  }

  async removeServicePincode(id: string) {
    const existing = await this.prisma.servicePincode.findFirst({ where: { id } });
    if (!existing) {
      return successResponse(null, 'Pincode not found');
    }

    await this.prisma.servicePincode.delete({ where: { id } });
    return successResponse(null, 'Service pincode removed successfully');
  }

  async checkServicePincode(pincode: string) {
    const match = await this.prisma.servicePincode.findFirst({ where: { pincode: pincode.trim() } });
    const serviceable = Boolean(match && match.isActive);
    return successResponse({ pincode: pincode.trim(), serviceable }, serviceable ? 'Pincode is serviceable' : 'Pincode is not serviceable');
  }
}
