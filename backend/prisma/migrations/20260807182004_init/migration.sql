-- AlterTable
ALTER TABLE "Category" ADD COLUMN     "iconUrl" TEXT,
ADD COLUMN     "isActive" BOOLEAN NOT NULL DEFAULT true;

-- AlterTable
ALTER TABLE "Product" ADD COLUMN     "discountedPrice" DECIMAL(10,2);

-- CreateTable
CREATE TABLE "StoreSetting" (
    "id" TEXT NOT NULL,
    "deliveryCharges" DECIMAL(10,2) NOT NULL,
    "tax" DECIMAL(10,2) NOT NULL,
    "appVersion" TEXT NOT NULL,
    "storeOpenTime" TEXT NOT NULL,
    "storeCloseTime" TEXT NOT NULL,
    "paymentMethods" TEXT[],
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "StoreSetting_pkey" PRIMARY KEY ("id")
);
