import { ArgumentsHost, Catch, ExceptionFilter } from '@nestjs/common';
import type { Response } from 'express';
import { Prisma } from '../../generated/prisma-client';
import { errorResponse } from '../api-response';

// Human-friendly names for columns that show up in Prisma's unique-constraint violations
// (Prisma.meta.target). Falls back to the raw field name when one isn't listed here.
const FIELD_LABELS: Record<string, string> = {
  slug: 'name',
  name: 'name',
  email: 'email address',
  phone: 'phone number',
  sku: 'SKU',
  pincode: 'pincode',
  code: 'code',
  token: 'token',
  webhookEventId: 'payment webhook event',
};

function describeFields(target: unknown): string {
  const fields = Array.isArray(target) ? (target as string[]) : typeof target === 'string' ? [target] : [];
  if (!fields.length) {
    return 'value';
  }
  return fields.map((field) => FIELD_LABELS[field] ?? field).join(', ');
}

type CatchablePrismaError = Prisma.PrismaClientKnownRequestError | Prisma.PrismaClientValidationError;

// Applies to every Prisma-backed endpoint app-wide, so individual services don't each need
// their own duplicate-entry / not-found / malformed-input handling for common database errors.
@Catch(Prisma.PrismaClientKnownRequestError, Prisma.PrismaClientValidationError)
export class PrismaExceptionFilter implements ExceptionFilter {
  catch(exception: CatchablePrismaError, host: ArgumentsHost) {
    const response = host.switchToHttp().getResponse<Response>();

    if (exception instanceof Prisma.PrismaClientValidationError) {
      // Thrown when a request sends data Prisma can't map onto the schema (wrong type, missing
      // required field, etc.) — a bug in the caller's payload, not a server fault, so 400.
      const message = 'Invalid request data. Please check the submitted values.';
      response.status(400).json(errorResponse(message, [message]));
      return;
    }

    if (exception.code === 'P2002') {
      const message = `A record with this ${describeFields(exception.meta?.target)} already exists`;
      response.status(409).json(errorResponse(message, [message]));
      return;
    }

    if (exception.code === 'P2025') {
      const message = 'The requested record was not found';
      response.status(404).json(errorResponse(message, [message]));
      return;
    }

    if (exception.code === 'P2003') {
      const message = 'This action references a record that no longer exists';
      response.status(409).json(errorResponse(message, [message]));
      return;
    }

    // eslint-disable-next-line no-console
    console.error('Unhandled Prisma error', exception);
    response.status(500).json(errorResponse('Something went wrong. Please try again.', []));
  }
}
