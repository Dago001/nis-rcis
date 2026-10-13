// Stand-in for the "WebSdk" module that @digitalpersona/devices imports. HID ships
// WebSdk as a browser script that defines window.WebSdk; digitalpersona.ts loads
// it from public/vendor/digitalpersona/ before the devices library is used.
// Wired up with turbopack.resolveAlias in next.config.ts.
export {};
