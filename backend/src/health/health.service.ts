import { Injectable } from '@nestjs/common';

@Injectable()
export class HealthService {
  getHealth() {
    return {
      success: true,
      message: 'Dailzo backend is running',
      data: {
        status: 'ok',
        timestamp: new Date().toISOString(),
        service: 'dailzo-backend',
      },
    };
  }
}
