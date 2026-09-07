import { CreateProductDto } from './create-product.dto';

// Not @nestjs/swagger's PartialType(CreateProductDto): that helper calls class-validator's
// IsOptional() at class-definition time, which doesn't exist in this project's installed
// class-validator (an old 0.5.x release) and would crash on import. The admin UI always sends
// the full product object on edit, so a plain extends (fields stay required) matches real usage.
export class UpdateProductDto extends CreateProductDto {}
