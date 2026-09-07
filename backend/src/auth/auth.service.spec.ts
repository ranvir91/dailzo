import { Test, TestingModule } from '@nestjs/testing';
import { PrismaService } from '../prisma/prisma.service';
import { AuthService } from './auth.service';

describe('AuthService', () => {
  let service: AuthService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        AuthService,
        {
          provide: PrismaService,
          useValue: {
            user: { findUnique: jest.fn(), create: jest.fn() },
            refreshToken: { create: jest.fn() },
          },
        },
      ],
    }).compile();

    service = module.get<AuthService>(AuthService);
  });

  it('logs in a user with a valid OTP', async () => {
    const prisma = service['prisma'];
    prisma.user.findUnique.mockResolvedValueOnce(null);
    prisma.user.create.mockResolvedValueOnce({ id: 'user-1', phone: '9999999999', role: 'CUSTOMER', name: 'New Customer' });
    prisma.refreshToken.create.mockResolvedValueOnce({});

    const response = await service.login({ phone: '9999999999', otp: '1234' });

    expect(response.success).toBe(true);
    expect(response.message).toBe('Login successful');
    expect(response.data.user.phone).toBe('9999999999');
    expect(response.data.accessToken).toContain('access-user-1');
    expect(response.data.refreshToken).toContain('refresh-user-1');
  });
});
