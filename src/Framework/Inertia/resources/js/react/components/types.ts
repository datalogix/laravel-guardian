export type IdentifierKey = 'email' | 'username' | 'cpf' | 'cnpj' | 'login'

export type TwoFactorMethod = 'totp' | 'email' | 'sms'

export interface OAuthProvider {
    name: string
    url: string
}

export interface TrustedDevice {
    id: number
    name: string | null
    ip_address: string | null
    last_used_at: string | null
    revokeUrl: string
}

/** The validation errors Inertia shares with every page, keyed by field. */
export type PageErrors = Record<string, string>
