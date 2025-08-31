# Build Process

This project uses npm and build tools to automatically minify CSS and JavaScript files for production.

## Prerequisites

Make sure you have Node.js and npm installed on your system.

## Installation

Install the required dependencies:

```bash
npm install
```

## Available Scripts

### Build Commands

- **`npm run build`** - Build Sass, CSS, and JS files (one-time)
- **`npm run watch`** - Watch for changes and rebuild automatically

### Individual Commands

- **`npm run build:sass`** - Build Sass to CSS only
- **`npm run build:css`** - Minify CSS only
- **`npm run build:js`** - Minify JavaScript only
- **`npm run watch:sass`** - Watch Sass changes only
- **`npm run watch:css`** - Watch CSS changes only
- **`npm run watch:js`** - Watch JavaScript changes only

### Manual Commands

You can also run the commands directly:

```bash
# Build Sass to CSS
npx sass assets/scss:assets/css --style=compressed

# Minify CSS
npx cleancss -o assets/css/style.min.css assets/css/style.css

# Minify JavaScript
npx terser assets/js/app.js -o assets/js/app.min.js --compress --mangle
```

## Development Workflow

1. **For development**: Edit `assets/scss/main.scss`, `assets/css/style.css`, and `assets/js/app.js`
2. **For production**: Run `npm run build` to create minified versions
3. **For continuous development**: Run `npm run watch` to automatically rebuild on changes

## File Structure

- `assets/scss/main.scss` - Source SCSS file
- `assets/css/main.css` - Compiled CSS file (generated from SCSS)
- `assets/css/style.css` - Source CSS file
- `assets/css/style.min.css` - Minified CSS file (generated)
- `assets/js/app.js` - Source JavaScript file
- `assets/js/app.min.js` - Minified JavaScript file (generated)

## Dependencies

- **sass**: SCSS compilation
- **clean-css-cli**: CSS minification
- **terser**: JavaScript minification and compression
- **nodemon**: File watching for development

## Notes

- The minified files are automatically used by the application
- Always run the build script before deploying to production
- The watch script is useful during development to automatically rebuild on changes
- Both npm scripts and shell scripts are available for flexibility
