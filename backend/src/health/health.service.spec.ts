import { HealthService } from './health.service';

describe('HealthService', () => {
  it('returns a healthy response payload', () => {
    const service = new HealthService();
    const response = service.getHealth();

    expect(response.success).toBe(true);
    expect(response.message).toBe('Dailzo backend is running');
    expect(response.data.status).toBe('ok');
  });
});
