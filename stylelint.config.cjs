module.exports = {
  extends: ['stylelint-config-standard'],
  ignoreFiles: [
    'node_modules/**/*',
    'vendor/**/*',
    'assets/**/*'
  ],
  rules: {
    'color-hex-case': 'lower',
    'selector-class-pattern': null
  }
};
