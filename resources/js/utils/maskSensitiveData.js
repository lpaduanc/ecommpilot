/**
 * Utility function to mask sensitive data (tokens, secrets, etc.)
 * Shows first 4 and last 4 characters, masking the middle with asterisks
 *
 * @param {string} value - The sensitive value to mask
 * @param {number} visibleChars - Number of characters to show at start and end (default: 4)
 * @returns {string} - Masked string
 *
 * @example
 * maskSensitiveData('abcdef123456789xyz') // 'abcd***...***9xyz'
 * maskSensitiveData('short', 2) // 'sh**rt'
 */
export function maskSensitiveData(value, visibleChars = 4) {
    if (!value || typeof value !== 'string') {
        return '';
    }

    // If value is too short, just mask the middle
    if (value.length <= visibleChars * 2) {
        const halfLength = Math.floor(value.length / 2);
        return value.substring(0, halfLength) + '***' + value.substring(value.length - halfLength);
    }

    const start = value.substring(0, visibleChars);
    const end = value.substring(value.length - visibleChars);

    return `${start}***...***${end}`;
}

/**
 * Mask email address showing first 3 chars and domain
 *
 * @param {string} email - Email to mask
 * @returns {string} - Masked email
 *
 * @example
 * maskEmail('john.doe@example.com') // 'joh***@example.com'
 */
export function maskEmail(email) {
    if (!email || typeof email !== 'string' || !email.includes('@')) {
        return email;
    }

    const [localPart, domain] = email.split('@');

    if (localPart.length <= 3) {
        return `${localPart[0]}***@${domain}`;
    }

    return `${localPart.substring(0, 3)}***@${domain}`;
}

/**
 * Mask phone number showing only last 4 digits
 *
 * @param {string} phone - Phone number to mask
 * @returns {string} - Masked phone
 *
 * @example
 * maskPhone('11999887766') // '***7766'
 */
export function maskPhone(phone) {
    if (!phone || typeof phone !== 'string') {
        return phone;
    }

    const cleanPhone = phone.replace(/\D/g, '');

    if (cleanPhone.length <= 4) {
        return phone;
    }

    return `***${cleanPhone.slice(-4)}`;
}

/**
 * Format sensitive configuration for display
 *
 * @param {Object} config - Configuration object
 * @returns {Object} - Masked configuration
 *
 * @example
 * maskConfiguration({
 *   clientId: '247130',
 *   clientSecret: 'very-long-secret-key',
 *   accessToken: 'access-token-123'
 * })
 * // Returns masked version of each field
 */
export function maskConfiguration(config) {
    if (!config || typeof config !== 'object') {
        return {};
    }

    const sensitiveFields = [
        'clientSecret',
        'client_secret',
        'accessToken',
        'access_token',
        'refreshToken',
        'refresh_token',
        'apiKey',
        'api_key',
        'password',
        'secret',
    ];

    const masked = {};

    Object.keys(config).forEach(key => {
        const lowerKey = key.toLowerCase();
        const isSensitive = sensitiveFields.some(field =>
            lowerKey.includes(field.toLowerCase())
        );

        masked[key] = isSensitive ? maskSensitiveData(config[key]) : config[key];
    });

    return masked;
}
