import { ApiProperty } from '@nestjs/swagger';

export class LoginDto {
  @ApiProperty({ example: '9999999999' })
  phone: string;

  @ApiProperty({ example: '1234' })
  otp: string;
}
