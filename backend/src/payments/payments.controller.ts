import { Body, Controller, Post, UseGuards } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { AuthGuard } from '../common/guards/auth.guard';
import { PaymentsService } from './payments.service';

@ApiTags('payments')
@Controller('payments')
export class PaymentsController {
  constructor(private readonly paymentsService: PaymentsService) {}

  @ApiBearerAuth()
  @UseGuards(AuthGuard)
  @Post('create')
  create(@Body() dto: any) {
    return this.paymentsService.create(dto);
  }

  // Called by the payment gateway server-to-server, not by a logged-in user — do not guard with
  // AuthGuard. Should be secured with the gateway's webhook signature once that's integrated.
  @Post('webhook')
  webhook(@Body() dto: any) {
    return this.paymentsService.webhook(dto);
  }
}
