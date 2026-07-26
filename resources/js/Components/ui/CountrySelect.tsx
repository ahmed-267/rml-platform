import { forwardRef } from 'react';
import {
    DEFAULT_COUNTRY,
    countrySelectOptions,
} from '@/lib/countries';
import { Select, type SelectProps } from './Select';

export interface CountrySelectProps
    extends Omit<SelectProps, 'options' | 'placeholder'> {
    locale?: string;
    placeholder?: string;
}

export const CountrySelect = forwardRef<
    HTMLSelectElement,
    CountrySelectProps
>(({ locale, value, placeholder, ...props }, ref) => {
    return (
        <Select
            ref={ref}
            options={countrySelectOptions(locale)}
            value={value ?? DEFAULT_COUNTRY}
            placeholder={placeholder}
            {...props}
        />
    );
});

CountrySelect.displayName = 'CountrySelect';
