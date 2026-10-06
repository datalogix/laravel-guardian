import js from '@eslint/js'
import eslintReact from '@eslint-react/eslint-plugin'
import { defineConfig } from 'eslint/config'
import reactHooks from 'eslint-plugin-react-hooks'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'
import tseslint from 'typescript-eslint'

// The default Inertia pages Guardian publishes, for Vue and for React.
const pages = 'src/Framework/Inertia/resources/js'

export default defineConfig([
    {
        files: [`${pages}/**/*.{ts,tsx,vue}`],
        extends: [js.configs.recommended, tseslint.configs.recommended],
        languageOptions: {
            globals: globals.browser,
        },
    },
    {
        files: [`${pages}/vue/**/*.{ts,vue}`],
        extends: [pluginVue.configs['flat/recommended']],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
            },
        },
        rules: {
            // The style of the pages: four spaces, and void elements closed like <input />.
            'vue/html-indent': ['warn', 4],
            'vue/html-self-closing': ['warn', { html: { void: 'always' } }],
            // Where attributes and content break is left to the formatter.
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            // The pages are named after the Inertia pages (Guardian/Login), and the
            // components are only used by them.
            'vue/multi-word-component-names': 'off',
            // An optional prop typed with TypeScript is undefined on purpose.
            'vue/require-default-prop': 'off',
        },
    },
    {
        files: [`${pages}/react/**/*.{ts,tsx}`],
        extends: [eslintReact.configs['recommended-typescript'], reactHooks.configs.flat.recommended],
        rules: {
            // The rules of hooks are left to the plugin of the React team, which also
            // brings those of the React Compiler.
            '@eslint-react/rules-of-hooks': 'off',
            '@eslint-react/exhaustive-deps': 'off',
        },
    },
])
