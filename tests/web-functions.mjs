import {frontendCases} from './support/frontend-cases.mjs';
await frontendCases(new URL('./web-functions.php',import.meta.url));
