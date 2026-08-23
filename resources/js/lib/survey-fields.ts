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
