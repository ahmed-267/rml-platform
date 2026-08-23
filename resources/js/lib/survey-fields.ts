export type SurveyStepId =
    | 'property'
    | 'measurements'
    | 'scheme'
    | 'evidence'
    | 'homeowner'
    | 'risks'
    | 'review';

export function optionLabel(
    translations: Record<string, string> | undefined,
    value: string,
): string {
    if (!value) {
        return '—';
    }
    return translations?.[value] ?? value.replaceAll('_', ' ');
}

export function toSelectOptions(
    values: string[],
    translations?: Record<string, string>,
    includeBlank = true,
): Array<{ value: string; label: string }> {
    const options = values.map((value) => ({
        value,
        label: optionLabel(translations, value),
    }));
    return includeBlank ? [{ value: '', label: '—' }, ...options] : options;
}

export function yesNoOptions(
    yesLabel: string,
    noLabel: string,
): Array<{ value: string; label: string }> {
    return [
        { value: '', label: '—' },
        { value: 'yes', label: yesLabel },
        { value: 'no', label: noLabel },
    ];
}

export function boolToYesNo(value: unknown): string {
    if (value === true || value === 1 || value === '1' || value === 'yes') {
        return 'yes';
    }
    if (value === false || value === 0 || value === '0' || value === 'no') {
        return 'no';
    }
    return '';
}

export function yesNoToBool(value: string): boolean | null {
    if (value === 'yes') {
        return true;
    }
    if (value === 'no') {
        return false;
    }
    return null;
}

export function isRequiredField(
    requiredFields: Record<string, string[]> | undefined,
    step: SurveyStepId,
    fieldKey: string,
): boolean {
    return Boolean(requiredFields?.[step]?.includes(fieldKey));
}

export function isMissingRequiredValue(key: string, value: unknown): boolean {
    if (
        key === 'location_confirmed' ||
        key.endsWith('permission_to_inspect') ||
        key.endsWith('permission_evidence')
    ) {
        return value !== true;
    }

    if (key === 'surveyed_installation_area_m2') {
        const numeric = typeof value === 'number' ? value : parseFloat(String(value ?? ''));
        return Number.isNaN(numeric) || numeric <= 0;
    }

    if (key === 'measurement_sections') {
        return !Array.isArray(value) || value.length === 0;
    }

    if (typeof value === 'boolean') {
        return false;
    }

    return value === null || value === undefined || value === '';
}

export function readNestedFormValue(
    data: Record<string, unknown>,
    key: string,
): unknown {
    if (!key.includes('.')) {
        return data[key];
    }

    const parts = key.split('.');
    let current: unknown = data;

    for (const part of parts) {
        if (!current || typeof current !== 'object') {
            return undefined;
        }
        current = (current as Record<string, unknown>)[part];
    }

    return current;
}

/** Replace Laravel-style :field / :category placeholders in survey error templates. */
export function formatSurveyError(
    template: string | undefined,
    replacements: Record<string, string>,
    fallback: string,
): string {
    let message = template && template.trim() !== '' ? template : fallback;
    for (const [key, value] of Object.entries(replacements)) {
        message = message.replaceAll(`:${key}`, value);
    }
    // Guard against unsubstituted placeholders showing as ":field is required."
    if (message.includes(':field') && replacements.field) {
        message = message.replaceAll(':field', replacements.field);
    }
    return message;
}

/** Map form error keys to human labels for required-message substitution. */
export function surveyFieldLabel(
    key: string,
    fields: Record<string, string> | undefined,
): string {
    const leaf = key.includes('.') ? (key.split('.').pop() ?? key) : key;
    const fromFields = fields?.[leaf] ?? fields?.[key];
    if (fromFields) {
        return fromFields;
    }
    return leaf.replaceAll('_', ' ');
}

/**
 * Scroll / focus the first invalid survey control.
 * Prefer data-error-field (set by FormInput/Select/Textarea via name).
 */
export function scrollToSurveyError(fieldKey: string | undefined): void {
    if (!fieldKey || typeof document === 'undefined') {
        return;
    }

    const tryFind = (): HTMLElement | null => {
        const selectors = [
            `[data-error-field="${CSS.escape(fieldKey)}"]`,
            `[name="${CSS.escape(fieldKey)}"]`,
            `[data-validation-summary]`,
        ];
        for (const selector of selectors) {
            try {
                const el = document.querySelector(selector);
                if (el instanceof HTMLElement) {
                    return el;
                }
            } catch {
                // ignore invalid selectors
            }
        }

        if (fieldKey.startsWith('measurement_sections.')) {
            const match = /^measurement_sections\.(\d+)/.exec(fieldKey);
            if (match) {
                const section = document.querySelector(
                    `[data-measurement-section="${match[1]}"]`,
                );
                if (section instanceof HTMLElement) {
                    return section;
                }
            }
        }

        return null;
    };

    window.requestAnimationFrame(() => {
        const el = tryFind();
        if (!el) {
            return;
        }
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const focusable =
            el.matches('input, select, textarea, button')
                ? el
                : el.querySelector<HTMLElement>('input, select, textarea, button');
        if (focusable && !focusable.hasAttribute('disabled')) {
            window.setTimeout(() => {
                focusable.focus({ preventScroll: true });
            }, 200);
        }
    });
}
