# Build Process

This project uses npm and build tools to automatically compile SASS and minify JavaScript files for production.

## Prerequisites

Make sure you have Node.js and npm installed on your system.

## Installation

Install the required dependencies:

```bash
npm install
```

## Available Scripts

### Build Commands

- **`npm run build`** - Build SASS and JS files (one-time)
- **`npm run watch`** - Watch for changes and rebuild automatically

### Individual Commands

- **`npm run build:sass`** - Build SASS to CSS only
- **`npm run build:js`** - Minify JavaScript only
- **`npm run watch:sass`** - Watch SASS changes only
- **`npm run watch:js`** - Watch JavaScript changes only

### Manual Commands

You can also run the commands directly:

```bash
# Build SASS to CSS
npx sass assets/scss:assets/css --style=compressed

# Minify JavaScript
npx terser assets/js/app.js -o assets/js/app.min.js --compress --mangle
```

## Development Workflow

1. **For development**: Edit `assets/scss/main.scss` and `assets/js/app.js`
2. **For production**: Run `npm run build` to create compiled and minified versions
3. **For continuous development**: Run `npm run watch` to automatically rebuild on changes

## File Structure

- `assets/scss/main.scss` - Source SCSS file (main entry point)
- `assets/scss/base/` - Base SASS files (variables, mixins, reset, typography)
- `assets/scss/components/` - Component-specific SASS files
- `assets/scss/layouts/` - Layout-specific SASS files
- `assets/scss/pages/` - Page-specific SASS files
- `assets/scss/utilities/` - Utility SASS files
- `assets/css/main.css` - Compiled CSS file (generated from SCSS)
- `assets/js/app.js` - Source JavaScript file
- `assets/js/app.min.js` - Minified JavaScript file (generated)

## SASS Architecture

The SASS files are organized using a modular architecture:

- **Base**: Variables, mixins, reset styles, and typography
- **Components**: Buttons, forms, modals, dropdowns, tables, badges, etc.
- **Layouts**: Header, container, and authentication controls
- **Pages**: Setup and collection page styles
- **Utilities**: Spacing, helpers, and responsive utilities

## Dependencies

- **sass**: SCSS compilation
- **terser**: JavaScript minification and compression
- **nodemon**: File watching for development

## Notes

- SASS automatically compresses CSS output with `--style=compressed`
- The compiled CSS file is automatically used by the application
- Always run the build script before deploying to production
- The watch script is useful during development to automatically rebuild on changes
- The SASS architecture follows best practices for maintainability and scalability
