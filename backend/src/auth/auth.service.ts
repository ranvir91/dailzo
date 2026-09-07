import { HttpException, HttpStatus, Injectable } from '@nestjs/common';
import { randomInt } from 'crypto';
import { errorResponse, successResponse } from '../common/api-response';
import { PrismaService } from '../prisma/prisma.service';
import { LoginDto } from './dto/login.dto';
import { RefreshTokenDto } from './dto/refresh-token.dto';
import { SendOtpDto } from './dto/send-otp.dto';
import { VerifyOtpDto } from './dto/verify-otp.dto';

@Injectable()
export class AuthService {
  private readonly otpStore = new Map<string, string>();

  constructor(private readonly prisma: PrismaService) {}

  async sendOtp(dto: SendOtpDto) {
    const otp = `${randomInt(1000, 9999)}`;
    this.otpStore.set(dto.phone, otp);
    return successResponse({ phone: dto.phone, otp, expiresIn: 300 }, 'OTP sent successfully');
  }

  async verifyOtp(dto: VerifyOtpDto) {
    const expectedOtp = this.otpStore.get(dto.phone);
    if (!expectedOtp || expectedOtp !== dto.otp) {
      throw new HttpException(errorResponse('Invalid OTP', ['The provided OTP is invalid or expired']), HttpStatus.BAD_REQUEST);
    }

    let user = await this.prisma.user.findUnique({ where: { phone: dto.phone } });
    if (!user) {
      user = await this.prisma.user.create({
        data: {
          phone: dto.phone,
          role: 'CUSTOMER',
          name: 'New Customer',
        },
      });
    }

    const accessToken = `access-${user.id}-${Date.now()}`;
    const refreshToken = `refresh-${user.id}-${Date.now()}`;
    await this.prisma.refreshToken.create({
      data: {
        token: refreshToken,
        userId: user.id,
        expiresAt: new Date(Date.now() + 1000 * 60 * 60 * 24 * 7),
      },
    });

    return successResponse({ user, accessToken, refreshToken }, 'OTP verified successfully');
  }

  async login(dto: LoginDto) {
    const expectedOtp = this.otpStore.get(dto.phone);
    if (!expectedOtp || expectedOtp !== dto.otp) {
      throw new HttpException(errorResponse('Invalid OTP', ['The provided OTP is invalid or expired']), HttpStatus.BAD_REQUEST);
    }

    let user = await this.prisma.user.findUnique({ where: { phone: dto.phone } });
    if (!user) {
      user = await this.prisma.user.create({ data: { phone: dto.phone, role: 'CUSTOMER', name: 'New Customer' } });
    }

    const accessToken = `access-${user.id}-${Date.now()}`;
    const refreshToken = `refresh-${user.id}-${Date.now()}`;
    await this.prisma.refreshToken.create({ data: { token: refreshToken, userId: user.id, expiresAt: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000) } });

    return successResponse({ user, accessToken, refreshToken }, 'Login successful');
  }

  async refreshToken(dto: RefreshTokenDto) {
    const tokenRecord = await this.prisma.refreshToken.findUnique({ where: { token: dto.refreshToken } });
    if (!tokenRecord || tokenRecord.expiresAt < new Date()) {
      throw new HttpException(errorResponse('Invalid refresh token', ['Refresh token is invalid']), HttpStatus.UNAUTHORIZED);
    }

    const user = await this.prisma.user.findUnique({ where: { id: tokenRecord.userId } });
    if (!user) {
      throw new HttpException(errorResponse('User not found', ['The user associated with this refresh token was not found']), HttpStatus.NOT_FOUND);
    }

    const accessToken = `access-${user.id}-${Date.now()}`;
    return successResponse({ user, accessToken }, 'Token refreshed successfully');
  }
}
