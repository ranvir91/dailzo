import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';

@Injectable()
export class DeliveryService {
  getPartners() {
    return successResponse([
      { id: 'partner-1', name: 'Ravi Kumar', phone: '9999999998' },
    ], 'Delivery partners fetched successfully');
  }

  assignPartner() {
    return successResponse({ assignedPartnerId: 'partner-1', status: 'ASSIGNED' }, 'Delivery partner assigned successfully');
  }

  updateStatus(id: string) {
    return successResponse({ id, status: 'OUT_FOR_DELIVERY' }, 'Delivery status updated successfully');
  }
}
