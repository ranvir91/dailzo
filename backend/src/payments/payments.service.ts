import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';

@Injectable()
export class PaymentsService {
  create(dto: any) {
    return successResponse({ paymentId: `pay-${Date.now()}`, status: 'PENDING', provider: dto.provider ?? 'mock' }, 'Payment created successfully');
  }

  webhook(dto: any) {
    return successResponse({ paymentId: dto.paymentId, status: 'SUCCESS' }, 'Payment webhook processed');
  }
}
