# Contributing to Music Collection Manager

Thank you for your interest in contributing to the Music Collection Manager! This document provides guidelines and information for contributors.

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/yourusername/music-collection-manager.git`
3. Create a feature branch: `git checkout -b feature/your-feature-name`
4. Make your changes
5. Test your changes thoroughly
6. Commit your changes: `git commit -m "Add your feature"`
7. Push to your fork: `git push origin feature/your-feature-name`
8. Create a Pull Request

## Development Setup

### Prerequisites
- PHP 7.4 or higher
- Node.js 10.12.0 or higher
- npm 6.0.0 or higher
- A Discogs API key (free at https://www.discogs.com/settings/developers)

### Installation
1. Clone the repository
2. Run `npm install` to install dependencies
3. Copy `config/api_config.php.example` to `config/api_config.php` and add your Discogs API key
4. Copy `config/auth_config.php.example` to `config/auth_config.php` and set your password
5. Ensure the `data/` directory is writable (755 permissions)
6. Run `npm run build` to build assets
7. Access the application via your web server

## Code Style

- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions focused and single-purpose
- Use consistent indentation (4 spaces for PHP, 2 spaces for JavaScript/CSS)

## Testing

Before submitting a pull request:
- Test all functionality manually
- Ensure responsive design works on mobile and desktop
- Verify accessibility features work correctly
- Check that all API integrations function properly
- Test with different browsers

## Feature Requests

When suggesting new features:
- Check existing issues first
- Provide a clear description of the feature
- Explain the use case and benefits
- Consider the impact on existing functionality

## Bug Reports

When reporting bugs:
- Use the issue template
- Provide steps to reproduce
- Include browser/OS information
- Add screenshots if applicable
- Check console for JavaScript errors

## Pull Request Guidelines

- Keep PRs focused and atomic
- Update documentation as needed
- Add tests if applicable
- Ensure all checks pass
- Request review from maintainers

## Areas for Contribution

- **Frontend**: UI/UX improvements, accessibility enhancements
- **Backend**: API optimizations, new features
- **Documentation**: README improvements, code comments
- **Testing**: Automated tests, manual testing
- **Performance**: Optimization, caching improvements
- **Security**: Security audits, vulnerability fixes

## Questions?

Feel free to open an issue for questions or discussions. We're here to help!
