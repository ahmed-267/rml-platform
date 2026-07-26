/** Phone helpers: digits + common separators only, 9–15 digits. */

const PHONE_ALLOWED = /^\+?[\d\s\-().]*$/;

export const PHONE_MIN_DIGITS = 9;
export const PHONE_MAX_DIGITS = 15;

export type PhoneErrorCode = 'required' | 'format' | 'too_short' | 'too_long';

export interface PhoneMessages {
    required?: string;
    format: string;
    too_short: string;
    too_long: string;
    length?: string;
}

export function sanitizePhoneInput(value: string): string {
    return value.replace(/[^\d+\s\-().]/g, '');
}

export function phoneDigitCount(value: string): number {
    return (value.match(/\d/g) ?? []).length;
}

export function isValidPhone(value: string | null | undefined): boolean {
    return phoneErrorCode(value) === null;
}

export function isEmptyOrValidPhone(value: string | null | undefined): boolean {
    if (value == null || value.trim() === '') {
        return true;
    }

    return isValidPhone(value);
}

export function phoneErrorCode(
    value: string | null | undefined,
    { required = false }: { required?: boolean } = {},
): PhoneErrorCode | null {
    if (value == null || value.trim() === '') {
        return required ? 'required' : null;
    }

    const trimmed = value.trim();

    if (!PHONE_ALLOWED.test(trimmed)) {
        return 'format';
    }

    const digits = phoneDigitCount(trimmed);

    if (digits < PHONE_MIN_DIGITS) {
        return 'too_short';
    }

    if (digits > PHONE_MAX_DIGITS) {
        return 'too_long';
    }

    return null;
}

export function phoneErrorMessage(
    value: string | null | undefined,
    messages: PhoneMessages,
    { required = false }: { required?: boolean } = {},
): string | null {
    const code = phoneErrorCode(value, { required });

    if (!code) {
        return null;
    }

    const digits = phoneDigitCount(value ?? '');

    switch (code) {
        case 'required':
            return messages.required ?? messages.format;
        case 'format':
            return messages.format;
        case 'too_short':
            return (
                messages.too_short
                    .replaceAll(':count', String(digits))
                    .replaceAll(':min', String(PHONE_MIN_DIGITS))
                    .replaceAll(':max', String(PHONE_MAX_DIGITS))
            );
        case 'too_long':
            return (
                messages.too_long
                    .replaceAll(':count', String(digits))
                    .replaceAll(':min', String(PHONE_MIN_DIGITS))
                    .replaceAll(':max', String(PHONE_MAX_DIGITS))
            );
        default:
            return messages.length ?? messages.format;
    }
}

export function phoneMessagesFromTranslations(
    validation?: {
        phone_format?: string;
        phone_length?: string;
        phone_too_short?: string;
        phone_too_long?: string;
        phone_required?: string;
    },
    fieldRequired?: (field: string) => string,
    phoneLabel = 'Phone',
): PhoneMessages {
    return {
        required:
            validation?.phone_required ??
            fieldRequired?.(phoneLabel) ??
            `${phoneLabel} is required.`,
        format:
            validation?.phone_format ??
            'Enter a valid phone number using digits only (optional + and spaces).',
        too_short:
            validation?.phone_too_short ??
            validation?.phone_length ??
            'Phone has only :count digits — enter between :min and :max digits.',
        too_long:
            validation?.phone_too_long ??
            validation?.phone_length ??
            'Phone has :count digits — enter between :min and :max digits.',
        length: validation?.phone_length,
    };
}
