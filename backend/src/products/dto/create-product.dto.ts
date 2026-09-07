import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Transform } from 'class-transformer';
import { IsInt, IsNotEmpty, IsNumber, IsString, Min } from 'class-validator';

// Admin forms send numeric fields as strings, and an empty string for "no value" (e.g. a
// cleared discount field) rather than omitting the key or sending null. Map '' to null (not
// undefined) so it still means "explicitly clear this field" on update, rather than "leave
// unchanged" — while no longer crashing Prisma's Decimal parser on save.
//
// Note: this project's installed class-validator is a very old 0.5.x release that predates
// @IsOptional()/@IsArray(), so optional fields below are intentionally left undecorated rather
// than validated — @Transform still runs regardless (it comes from class-transformer), which is
// what actually prevents the crash.
const toOptionalNumber = ({ value }: { value: unknown }) => {
  if (value === '') {
    return null;
  }
  if (value === null || value === undefined) {
    return value;
  }
  const parsed = Number(value);
  return Number.isNaN(parsed) ? value : parsed;
};

// sortOrder is a plain (non-nullable) Int column with a DB default of 0, unlike discountedPrice
// above — so an empty string here maps to `undefined` (falls through to the column default / "leave
// unchanged" on update) rather than `null`, which the column doesn't accept.
const toOptionalInt = ({ value }: { value: unknown }) => {
  if (value === '' || value === null || value === undefined) {
    return undefined;
  }
  const parsed = Number(value);
  return Number.isNaN(parsed) ? undefined : Math.trunc(parsed);
};

export class CreateProductDto {
  @ApiProperty({ example: 'Fresh Milk' })
  @IsString()
  @IsNotEmpty()
  name!: string;

  @ApiPropertyOptional({ example: 'Organic milk' })
  description?: string;

  @ApiPropertyOptional({ example: 'dairy' })
  category?: string;

  @ApiPropertyOptional({ example: 'category-id-1' })
  categoryId?: string;

  @ApiProperty({ example: 70 })
  @Transform(toOptionalNumber)
  @IsNumber()
  @Min(0)
  price!: number;

  @ApiPropertyOptional({ example: 60 })
  @Transform(toOptionalNumber)
  discountedPrice?: number;

  @ApiProperty({ example: 20 })
  @Transform(toOptionalNumber)
  @IsInt()
  @Min(0)
  stock!: number;

  @ApiPropertyOptional({ example: ['https://example.com/milk.png'] })
  images?: string[];

  @ApiPropertyOptional({ example: 0, description: 'Lower numbers are shown first on the storefront.' })
  @Transform(toOptionalInt)
  sortOrder?: number;
}
