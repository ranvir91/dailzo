import { Module } from '@nestjs/common';
import { AddressesModule } from './addresses/addresses.module';
import { AdminModule } from './admin/admin.module';
import { AuthModule } from './auth/auth.module';
import { CartModule } from './cart/cart.module';
import { CategoriesModule } from './categories/categories.module';
import { CouponsModule } from './coupons/coupons.module';
import { DeliveryModule } from './delivery/delivery.module';
import { HealthModule } from './health/health.module';
import { NotificationsModule } from './notifications/notifications.module';
import { OrdersModule } from './orders/orders.module';
import { PaymentsModule } from './payments/payments.module';
import { ProductsModule } from './products/products.module';
import { SettingsModule } from './settings/settings.module';
import { UploadsModule } from './uploads/uploads.module';
import { UsersModule } from './users/users.module';

@Module({
  imports: [
    HealthModule,
    AuthModule,
    ProductsModule,
    CategoriesModule,
    CartModule,
    AddressesModule,
    OrdersModule,
    PaymentsModule,
    SettingsModule,
    CouponsModule,
    DeliveryModule,
    NotificationsModule,
    AdminModule,
    UploadsModule,
    UsersModule,
  ],
})
export class AppModule {}
