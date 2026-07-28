export type RejectionReasonOption = {
    value: string;
    label: string;
};

export function rejectionReasonOptions(
    reasons: Record<string, string> | undefined,
): RejectionReasonOption[] {
    if (!reasons) {
        return [];
    }

    return Object.entries(reasons).map(([value, label]) => ({
        value,
        label,
    }));
}
