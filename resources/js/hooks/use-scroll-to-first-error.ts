import { useEffect } from 'react';

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

        const field = keys[0];
        const selectors = [
            `[name="${field}"]`,
            `[name="${field}[]"]`,
            `[data-error-field="${field}"]`,
            `#${CSS.escape(field)}`,
            // Nested metrics.foo → metrics[foo] style names rarely used; try dotted name.
            `[name="${field.replace(/\./g, '.')}"]`,
        ];

        let el: HTMLElement | null = null;
        for (const selector of selectors) {
            try {
                el = document.querySelector(selector);
            } catch {
                el = null;
            }
            if (el) {
                break;
            }
        }

        if (!el && field.includes('.')) {
            const leaf = field.split('.').pop();
            if (leaf) {
                el = document.querySelector(`[name$=".${leaf}"]`);
            }
        }

        if (!el) {
            el = document.querySelector('[data-validation-summary]');
        }

        if (!el) {
            return;
        }

        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (
            'focus' in el &&
            typeof (el as HTMLElement).focus === 'function' &&
            !el.hasAttribute('disabled')
        ) {
            window.setTimeout(() => {
                (el as HTMLElement).focus({ preventScroll: true });
            }, 200);
        }
    }, [errors]);
}
