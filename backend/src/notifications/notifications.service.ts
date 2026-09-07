import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';

@Injectable()
export class NotificationsService {
  send(dto: any) {
    return successResponse({ sent: true, channel: dto.channel ?? 'fcm', event: dto.event ?? 'ORDER_CONFIRMED' }, 'Notification queued successfully');
  }
}
