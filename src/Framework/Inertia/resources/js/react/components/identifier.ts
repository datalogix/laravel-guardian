import type { Translator } from './translate'
import type { IdentifierKey } from './types'

export interface IdentifierField {
    label: string
    type: 'email' | 'text'
    autocomplete: string
    inputmode: 'email' | 'numeric' | 'text'
    placeholder?: string
}

const fields: Record<IdentifierKey, IdentifierField> = {
    email: { label: 'Email', type: 'email', autocomplete: 'username', inputmode: 'email', placeholder: 'email@example.com' },
    username: { label: 'Username', type: 'text', autocomplete: 'username', inputmode: 'text' },
    cpf: { label: 'CPF', type: 'text', autocomplete: 'off', inputmode: 'numeric', placeholder: '000.000.000-00' },
    cnpj: { label: 'CNPJ', type: 'text', autocomplete: 'off', inputmode: 'numeric', placeholder: '00.000.000/0000-00' },
    login: { label: 'Login', type: 'text', autocomplete: 'username', inputmode: 'text' },
}

/** How the field of the login identifier the fortress uses is labelled and typed. */
export function identifierField(key: IdentifierKey, t: Translator): IdentifierField {
    const field = fields[key] ?? fields.login

    return { ...field, label: t(field.label) }
}
