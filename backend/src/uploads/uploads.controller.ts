import { Controller, Post, UploadedFile, UseGuards, UseInterceptors } from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { diskStorage } = require('multer');
import { mkdirSync } from 'fs';
import { extname } from 'path';
import { AdminGuard } from '../common/guards/admin.guard';
import { UploadsService } from './uploads.service';

@ApiTags('uploads')
@ApiBearerAuth()
@UseGuards(AdminGuard)
@Controller('uploads')
export class UploadsController {
  constructor(private readonly uploadsService: UploadsService) {}

  @Post()
  @UseInterceptors(
    FileInterceptor('file', {
      storage: diskStorage({
        destination: (_req: any, _file: any, callback: any) => {
          const uploadsDir = 'uploads';
          mkdirSync(uploadsDir, { recursive: true });
          callback(null, uploadsDir);
        },
        filename: (_req: any, file: any, callback: any) => {
          const safeExt = extname(file.originalname) || '.png';
          const name = `${Date.now()}-${Math.round(Math.random() * 1e9)}${safeExt}`;
          callback(null, name);
        },
      }),
      limits: {
        fileSize: 8 * 1024 * 1024,
      },
    }),
  )
  uploadFile(@UploadedFile() file: any) {
    return this.uploadsService.uploadFile(file);
  }
}
