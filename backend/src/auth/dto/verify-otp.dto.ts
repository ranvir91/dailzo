import { ApiProperty } from '@nestjs/swagger';
import { IsNotEmpty, IsString, Length } from 'class-validator';

export class VerifyOtpDto {
  @ApiProperty({ example: '9999999999' })
  @IsString()
  @IsNotEmpty()
  phone: string;

  @ApiProperty({ example: '1234' })
  @IsString()
  @IsNotEmpty()
  @Length(4, 4)
  otp: string;
}
