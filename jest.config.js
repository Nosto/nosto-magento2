module.exports = {
  verbose: true,
  testEnvironment: 'jsdom',
  moduleFileExtensions: ['js', 'jsx'],
  moduleNameMapper: {
    'Nosto_Tagging/js/(.*)': '<rootDir>/view/frontend/web/js/$1'
  },
  transform: {
    '^.+\\.js$': 'babel-jest'
  },
  collectCoverage: true,
  coverageDirectory: 'coverage',
  coverageReporters: ['text', 'lcov'],
  testMatch: ['<rootDir>/Test/Javascript/**/*.test.js'],
  setupFilesAfterEnv: ['<rootDir>/Test/Javascript/setup.js']
};
