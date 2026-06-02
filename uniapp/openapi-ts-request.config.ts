import { defineConfig } from 'openapi-ts-request'

const apidocBaseUrl = process.env.APIDOC_OPENAPI_BASE_URL ?? 'http://127.0.0.1:8787'

const createApidocSchemaPath = (key: string) =>
  `${apidocBaseUrl}/apidoc/openapi/${encodeURIComponent(key)}`

export default defineConfig([
  {
    describe: 'saiuser-api',
    schemaPath: createApidocSchemaPath('saiuser-api'),
    serversPath: './src/service/saiuser',
    requestLibPath: `import request from '@/http/vue-query';\n import { CustomRequestOptions_ } from '@/http/types';`,
    requestOptionsType: 'CustomRequestOptions_',
    isGenReactQuery: false,
    reactQueryMode: 'vue',
    isGenJavaScript: false,
  },
  {
    describe: 'saiai-api',
    schemaPath: createApidocSchemaPath('saiai-api'),
    serversPath: './src/service/saiai',
    requestLibPath: `import request from '@/http/vue-query';\n import { CustomRequestOptions_ } from '@/http/types';`,
    requestOptionsType: 'CustomRequestOptions_',
    isGenReactQuery: false,
    reactQueryMode: 'vue',
    isGenJavaScript: false,
  },
  {
    describe: 'saipay-api',
    schemaPath: createApidocSchemaPath('saipay-api'),
    serversPath: './src/service/saipay',
    requestLibPath: `import request from '@/http/vue-query';\n import { CustomRequestOptions_ } from '@/http/types';`,
    requestOptionsType: 'CustomRequestOptions_',
    isGenReactQuery: false,
    reactQueryMode: 'vue',
    isGenJavaScript: false,
  },
])
