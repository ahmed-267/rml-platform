import { useEffect } from 'react';
import { scrollToSurveyError } from '@/lib/survey-fields';

/**
 * Scroll and focus the first field matching Inertia/Laravel validation errors.
 */
export function useScrollToFirstError(
    errors: Record<string, string | string[] | undefined>,
): void {
    useEffect(() => {
        const keys = Object.keys(errors).filter((key) => {
            const value = errors[key];
            return Array.isArray(value) ? value.length > 0 : Boolean(value);
        });

        if (keys.length === 0) {
            return;
        }

        scrollToSurveyError(keys[0]);
    }, [errors]);
}
