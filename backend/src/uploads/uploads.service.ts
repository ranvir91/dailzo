import { Injectable } from '@nestjs/common';
import { successResponse } from '../common/api-response';

@Injectable()
export class UploadsService {
  uploadFile(file: any) {
    if (!file) {
      return successResponse(null, 'No file received');
    }

    const url = `uploads/${file.filename}`;
    return successResponse({ fileName: file.originalname, storedAs: file.filename, url }, 'File uploaded successfully');
  }
}
