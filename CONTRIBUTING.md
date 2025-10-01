# Contributing to LemricPluginBundle Marketplace

Thank you for considering contributing to the marketplace!

## Ways to Contribute

1. **Submit a Plugin** - Share your plugin with the community
2. **Report Issues** - Help us identify problems
3. **Improve Documentation** - Make it easier for others
4. **Review Submissions** - Help validate new plugins

## Plugin Submission Guidelines

### Before You Submit

- Ensure your plugin works with latest Symfony and PHP versions
- Test thoroughly in a real application
- Write clear documentation
- Follow security best practices

### Submission Process

1. **Fork** the repository
2. **Create** your plugin directory in `plugins/`
3. **Add** required files (plugin.json, README.md, releases/*.zip)
4. **Test** locally with validation script
5. **Submit** Pull Request
6. **Respond** to feedback from maintainers

### Required Files

```
plugins/your-plugin/
├── plugin.json       # REQUIRED: Manifest
├── README.md         # REQUIRED: Documentation  
├── CHANGELOG.md      # RECOMMENDED: Version history
└── releases/
    └── 1.0.0.zip    # REQUIRED: Packaged code
```

### plugin.json Requirements

Must include:
- `name` - Unique identifier (kebab-case)
- `version` - Semantic version
- `description` - Clear, concise description
- `author` - Your name or company
- `license` - Valid SPDX license identifier
- `mainClass` - FQN of main plugin class

### Code Quality Standards

- **PSR-12** coding style
- **Type declarations** for all parameters and return types
- **PHPDoc** for public methods
- **Error handling** with proper exceptions
- **Input validation** for all user inputs

### Security Requirements

**Must NOT include:**
- eval(), exec(), system(), shell_exec()
- Obfuscated or encoded code
- Unauthorized network requests
- Hardcoded credentials
- SQL injection vulnerabilities

**Must include:**
- Input sanitization
- Output escaping
- Prepared statements for DB queries
- CSRF protection where applicable

### Documentation Standards

Your README.md should include:

1. **Description** - What does it do?
2. **Installation** - How to install
3. **Configuration** - Available options
4. **Usage** - Code examples
5. **Hooks** - Available actions/filters
6. **Troubleshooting** - Common issues
7. **License** - License information

### Testing Your Plugin

Before submitting, test:

```bash
# Validate plugin structure
composer validate-plugin plugins/your-plugin

# Build marketplace index
composer build

# Check for errors
echo $?
```

### Version Updates

When updating your plugin:

1. Increment version in `plugin.json`
2. Add new ZIP to `releases/`
3. Update `CHANGELOG.md`
4. Create PR with clear description of changes

### Review Process

1. **Automated checks** run on PR
2. **Manual review** by maintainers
3. **Feedback** provided if issues found
4. **Approval** and merge when ready
5. **Live** within minutes of merge

### Code of Conduct

- Be respectful and constructive
- Provide helpful feedback
- Welcome newcomers
- Follow community guidelines

### Getting Help

- 📚 Read [Plugin Development Guide](./PLUGIN_DEVELOPMENT.md)
- 💬 Join [Discussions](https://github.com/lemric/plugin-marketplace/discussions)

## Questions?

If you have questions about the contribution process, feel free to:
- Open an issue
- Start a discussion
- Contact maintainers

Thank you for contributing! 🎉