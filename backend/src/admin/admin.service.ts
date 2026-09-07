import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';

@Injectable()
export class AdminService {
  getDashboard() {
    return successResponse({ totalOrders: 12, totalCustomers: 8, pendingOrders: 3 }, 'Admin dashboard data fetched successfully');
  }

  getOrders() {
    return successResponse([{ id: 'order-1', status: 'CONFIRMED' }], 'Admin orders fetched successfully');
  }
}
