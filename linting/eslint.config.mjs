import security from "eslint-plugin-security";
import noUnsanitized from "eslint-plugin-no-unsanitized";
import globals from "globals";

export default [
  {
    // Target only our custom plugin JS files (non-minified)
    files: [
      "../wp-content/plugins/ci-billing-indep/js/**/*.js",
      "../wp-content/plugins/ci-cache-and-security/js/**/*.js",
      "../wp-content/plugins/ci-data-works-arc/js/**/*.js",
      "../wp-content/plugins/ci-lab-client/js/**/*.js",
      "../wp-content/plugins/ci-otp-authenticator/js/**/*.js",
      "../wp-content/plugins/ci_patients/js/**/*.js",
    ],

    // Ignore minified and third-party files
    ignores: [
      "**/*.min.js",
      "**/vendor/**",
      "**/node_modules/**",
      "**/libraries/**",
      // Known third-party libraries
      "**/Chart.js",
      "**/Chart*.js",
      "**/select2*.js",
      "**/jquery.*.js",
      "**/tinymce-*.js",
      "**/d3-*.js",
      "**/canvasjs*.js",
      "**/signature_pad*.js",
      "**/BrowserPrint*.js",
      "**/DYMO*.js",
      "**/dymo-label*.js",
      "**/pdf.js",
      "**/pdf.worker.js",
      "**/fullcalendar/**",
      "**/markerclusterer.js",
      "**/gmap3*.js",
      "**/chartjs-plugin-*.js",
    ],

    languageOptions: {
      ecmaVersion: 2022,
      sourceType: "script", // Traditional scripts, not ES modules
      globals: {
        ...globals.browser,
        ...globals.jquery,
        // WordPress globals
        wp: "readonly",
        wpApiSettings: "readonly",
        ajaxurl: "readonly",
        pagenow: "readonly",
        typenow: "readonly",
        adminpage: "readonly",
        // Common WP/plugin globals
        tb_show: "readonly",
        tb_remove: "readonly",
        tinyMCE: "readonly",
        tinymce: "readonly",
      },
    },

    plugins: {
      security,
      "no-unsanitized": noUnsanitized,
    },

    rules: {
      // --- eslint-plugin-security ---
      "security/detect-eval-with-expression": "error",
      "security/detect-non-literal-regexp": "warn",
      "security/detect-unsafe-regex": "error",
      "security/detect-object-injection": "warn",

      // --- eslint-plugin-no-unsanitized (Mozilla) ---
      // Flags innerHTML, insertAdjacentHTML, outerHTML, document.write
      "no-unsanitized/method": "error",
      "no-unsanitized/property": "error",

      // --- Built-in ESLint security-relevant rules ---
      "no-eval": "error",
      "no-implied-eval": "error",
      "no-new-Function": "error",
      "no-script-url": "error",
    },
  },
];
